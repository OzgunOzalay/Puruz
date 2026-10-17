<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Support\Money;
use App\Support\Settings;
use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\Cancel;
use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\Currency;
use Iyzipay\Model\Locale;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Model\Refund;
use Iyzipay\Options;
use Iyzipay\Request\CreateCancelRequest;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzipay\Request\CreateRefundRequest;
use Iyzipay\Request\RetrieveCheckoutFormRequest;
use Throwable;

/**
 * iyzico hosted Checkout Form. Card details are entered on iyzico's page (with 3D Secure),
 * so they never reach this server.
 */
class IyzicoGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $secretKey,
        private readonly string $baseUrl,
    ) {}

    private function options(): Options
    {
        if ($this->apiKey === '' || $this->secretKey === '') {
            throw new PaymentException('iyzico API anahtarları tanımlı değil (IYZICO_API_KEY / IYZICO_SECRET_KEY).');
        }

        $options = new Options;
        $options->setApiKey($this->apiKey);
        $options->setSecretKey($this->secretKey);
        $options->setBaseUrl($this->baseUrl);

        return $options;
    }

    public function initialize(Order $order, string $callbackUrl, string $ip): InitializeResult
    {
        $order->loadMissing('items');
        $total = Money::toDecimal($order->total);

        $request = new CreateCheckoutFormInitializeRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId($order->number);
        $request->setPrice($total);
        $request->setPaidPrice($total);
        $request->setCurrency(Currency::TL);
        $request->setBasketId($order->number);
        $request->setPaymentGroup(PaymentGroup::PRODUCT);
        $request->setCallbackUrl($callbackUrl);
        $request->setEnabledInstallments($this->installments());

        [$firstName, $lastName] = $this->splitName($order->customer_name);

        $buyer = new Buyer;
        $buyer->setId('order-'.$order->id);
        $buyer->setName($firstName);
        $buyer->setSurname($lastName);
        $buyer->setGsmNumber($order->customer_phone);
        $buyer->setEmail($order->customer_email);
        // iyzico requires an identity number; 11111111111 is their accepted placeholder when none is collected.
        $buyer->setIdentityNumber($order->identity_number ?: '11111111111');
        $buyer->setRegistrationAddress($this->billingAddressLine($order));
        $buyer->setIp($ip);
        $buyer->setCity($order->billing_city ?: ($order->shipping_city ?: 'İstanbul'));
        $buyer->setCountry('Turkey');
        $request->setBuyer($buyer);

        $billing = new Address;
        $billing->setContactName($order->billing_type === 'corporate' && $order->billing_company ? $order->billing_company : ($order->billing_name ?: $order->customer_name));
        $billing->setCity($order->billing_city ?: ($order->shipping_city ?: 'İstanbul'));
        $billing->setCountry('Turkey');
        $billing->setAddress($this->billingAddressLine($order));
        $request->setBillingAddress($billing);

        if ($order->requires_shipping) {
            $shipping = new Address;
            $shipping->setContactName($order->shipping_name ?: $order->customer_name);
            $shipping->setCity($order->shipping_city);
            $shipping->setCountry('Turkey');
            $shipping->setAddress($order->shipping_address.' '.$order->shipping_district.'/'.$order->shipping_city);
            $shipping->setZipCode($order->shipping_postal_code);
            $request->setShippingAddress($shipping);
        }

        $items = [];
        foreach (BasketBuilder::lines($order) as $line) {
            $item = new BasketItem;
            $item->setId($line['id']);
            $item->setName($line['name']);
            $item->setCategory1($line['category']);
            $item->setItemType($line['type']);
            $item->setPrice(Money::toDecimal($line['price']));
            $items[] = $item;
        }
        $request->setBasketItems($items);

        try {
            $response = CheckoutFormInitialize::create($request, $this->options());
        } catch (Throwable $e) {
            throw new PaymentException('Ödeme sistemine bağlanılamadı. Lütfen tekrar deneyin.', previous: $e);
        }

        if ($response->getStatus() !== 'success' || ! $response->getPaymentPageUrl()) {
            throw new PaymentException($response->getErrorMessage() ?: 'Ödeme başlatılamadı.');
        }

        $expected = $this->sign([$response->getConversationId(), $response->getToken()]);
        if ($response->getSignature() && ! hash_equals($expected, (string) $response->getSignature())) {
            throw new PaymentException('Ödeme sisteminden gelen yanıt doğrulanamadı.');
        }

        return new InitializeResult($response->getToken(), $response->getPaymentPageUrl());
    }

    public function retrieve(string $token): PaymentResult
    {
        $request = new RetrieveCheckoutFormRequest;
        $request->setLocale(Locale::TR);
        $request->setToken($token);

        try {
            $form = CheckoutForm::retrieve($request, $this->options());
        } catch (Throwable $e) {
            throw new PaymentException('Ödeme sonucu alınamadı.', previous: $e);
        }

        $raw = json_decode((string) $form->getRawResult(), true) ?: [];

        if ($form->getStatus() !== 'success') {
            return new PaymentResult(
                successful: false,
                signatureValid: false,
                paymentId: null,
                conversationId: $form->getConversationId(),
                basketId: null,
                paidPrice: null,
                price: null,
                currency: null,
                errorMessage: $form->getErrorMessage() ?: 'Ödeme tamamlanamadı.',
                raw: $raw,
            );
        }

        // Signature fields and order as documented by iyzico for the checkout form result.
        $expected = $this->sign([
            $form->getPaymentStatus(),
            $form->getPaymentId(),
            $form->getCurrency(),
            $form->getBasketId(),
            $form->getConversationId(),
            $form->getPaidPrice(),
            $form->getPrice(),
            $form->getToken(),
        ]);

        $transactionIds = [];
        foreach ($form->getPaymentItems() ?? [] as $item) {
            $transactionIds[$item->getItemId()] = (string) $item->getPaymentTransactionId();
        }

        return new PaymentResult(
            successful: $form->getPaymentStatus() === 'SUCCESS',
            signatureValid: hash_equals($expected, (string) $form->getSignature()),
            paymentId: $form->getPaymentId() ? (string) $form->getPaymentId() : null,
            conversationId: $form->getConversationId(),
            basketId: $form->getBasketId(),
            paidPrice: Money::parse($form->getPaidPrice()),
            price: Money::parse($form->getPrice()),
            currency: $form->getCurrency(),
            installment: $form->getInstallment() ? (int) $form->getInstallment() : null,
            cardFamily: $form->getCardFamily(),
            cardAssociation: $form->getCardAssociation(),
            lastFour: $form->getLastFourDigits(),
            transactionIds: $transactionIds,
            errorMessage: $form->getPaymentStatus() === 'SUCCESS' ? null : ($form->getErrorMessage() ?: 'Ödeme onaylanmadı.'),
            raw: $raw,
        );
    }

    public function cancel(Order $order, string $ip): RefundResult
    {
        $request = new CreateCancelRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId($order->number);
        $request->setPaymentId($order->payment_id);
        $request->setIp($ip);

        try {
            $response = Cancel::create($request, $this->options());
        } catch (Throwable $e) {
            return new RefundResult(false, errorMessage: $e->getMessage());
        }

        return $response->getStatus() === 'success'
            ? new RefundResult(true, (string) $response->getPaymentId())
            : new RefundResult(false, errorMessage: $response->getErrorMessage());
    }

    public function refund(Order $order, string $transactionId, int $amount, string $ip): RefundResult
    {
        $request = new CreateRefundRequest;
        $request->setLocale(Locale::TR);
        $request->setConversationId($order->number);
        $request->setPaymentTransactionId($transactionId);
        $request->setPrice(Money::toDecimal($amount));
        $request->setCurrency(Currency::TL);
        $request->setIp($ip);

        try {
            $response = Refund::create($request, $this->options());
        } catch (Throwable $e) {
            return new RefundResult(false, errorMessage: $e->getMessage());
        }

        return $response->getStatus() === 'success'
            ? new RefundResult(true, (string) $response->getPaymentTransactionId())
            : new RefundResult(false, errorMessage: $response->getErrorMessage());
    }

    public function verifyWebhook(array $payload, ?string $signature): bool
    {
        if (! $signature || $this->secretKey === '') {
            return false;
        }

        $data = $this->secretKey
            .($payload['iyziEventType'] ?? '')
            .($payload['iyziPaymentId'] ?? '')
            .($payload['token'] ?? '')
            .($payload['paymentConversationId'] ?? '')
            .($payload['status'] ?? '');

        return hash_equals(bin2hex(hash_hmac('sha256', $data, $this->secretKey, true)), strtolower($signature));
    }

    private function sign(array $parts): string
    {
        return bin2hex(hash_hmac('sha256', implode(':', $parts), $this->secretKey, true));
    }

    private function installments(): array
    {
        if (! Settings::get('installments_enabled')) {
            return [1];
        }

        return array_values(array_filter([1, 2, 3, 6, 9, 12], fn ($n) => $n <= (int) Settings::get('max_installment', 6)));
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [$name];
        if (count($parts) === 1) {
            return [$parts[0], $parts[0]];
        }

        $last = array_pop($parts);

        return [implode(' ', $parts), $last];
    }

    private function billingAddressLine(Order $order): string
    {
        if ($order->billing_address) {
            return trim($order->billing_address.' '.$order->billing_district.'/'.$order->billing_city);
        }

        if ($order->shipping_address) {
            return trim($order->shipping_address.' '.$order->shipping_district.'/'.$order->shipping_city);
        }

        return $order->billing_city ?: 'Türkiye';
    }
}
