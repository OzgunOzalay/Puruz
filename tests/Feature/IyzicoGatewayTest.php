<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\Payment\IyzicoGateway;
use App\Services\Payment\PaymentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Iyzipay\ApiResource;
use Iyzipay\HttpClient;
use Tests\TestCase;

/**
 * Exercises the real iyzico gateway with the SDK's HTTP layer replaced, so request building
 * and signature checks are tested without network access or credentials.
 */
class IyzicoGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sandbox-secret-key';

    private FakeIyzicoHttp $http;

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = new FakeIyzicoHttp;
        ApiResource::setHttpClient($this->http);
    }

    protected function tearDown(): void
    {
        ApiResource::setHttpClient(null);
        parent::tearDown();
    }

    private function gateway(): IyzicoGateway
    {
        return new IyzicoGateway('sandbox-api-key', self::SECRET, 'https://sandbox-api.iyzipay.com');
    }

    private function order(): Order
    {
        $order = Order::factory()->create([
            'status' => 'pending_payment', 'subtotal' => 103500, 'shipping_total' => 9000, 'total' => 112500,
        ]);
        $order->items()->create(['name' => 'Kobalt Kupa', 'unit_price' => 65000, 'quantity' => 1, 'line_total' => 65000, 'charged_total' => 58500]);
        $order->items()->create(['name' => 'Kum Kupa', 'unit_price' => 50000, 'quantity' => 1, 'line_total' => 50000, 'charged_total' => 45000]);

        return $order->fresh('items');
    }

    private static function sign(array $parts): string
    {
        return bin2hex(hash_hmac('sha256', implode(':', $parts), self::SECRET, true));
    }

    public function test_initialize_sends_a_basket_that_adds_up(): void
    {
        $order = $this->order();
        $this->http->responses[] = [
            'status' => 'success', 'conversationId' => $order->number, 'token' => 'tok-1',
            'paymentPageUrl' => 'https://sandbox-cpp.iyzipay.com?token=tok-1',
            'signature' => self::sign([$order->number, 'tok-1']),
        ];

        $result = $this->gateway()->initialize($order, 'https://shop.test/odeme/donus', '85.34.78.112');

        $this->assertSame('tok-1', $result->token);
        $this->assertSame('https://sandbox-cpp.iyzipay.com?token=tok-1', $result->paymentPageUrl);

        $sent = json_decode($this->http->requests[0]['content'], true);
        $this->assertStringEndsWith('/payment/iyzipos/checkoutform/initialize/auth/ecom', $this->http->requests[0]['url']);
        $this->assertEquals(1125, (float) $sent['price']);
        $this->assertEquals(1125, (float) $sent['paidPrice']);
        $this->assertSame('TRY', $sent['currency']);
        $this->assertSame($order->number, $sent['basketId']);
        $this->assertEqualsWithDelta(1125, array_sum(array_map(fn ($i) => (float) $i['price'], $sent['basketItems'])), 0.001);
        $this->assertSame(['PHYSICAL', 'PHYSICAL', 'VIRTUAL'], array_column($sent['basketItems'], 'itemType'));
        $this->assertSame('+905321234567', $sent['buyer']['gsmNumber']);
        $this->assertSame('11111111111', $sent['buyer']['identityNumber']);
        $this->assertSame([1], $sent['enabledInstallments']);
        $this->assertArrayHasKey('shippingAddress', $sent);
    }

    public function test_initialize_rejects_a_forged_response(): void
    {
        $order = $this->order();
        $this->http->responses[] = [
            'status' => 'success', 'conversationId' => $order->number, 'token' => 'tok-1',
            'paymentPageUrl' => 'https://evil.example', 'signature' => 'deadbeef',
        ];

        $this->expectException(PaymentException::class);
        $this->gateway()->initialize($order, 'https://shop.test/odeme/donus', '127.0.0.1');
    }

    public function test_retrieve_verifies_signature_and_maps_transactions(): void
    {
        $order = $this->order();
        $payload = [
            'status' => 'success', 'paymentStatus' => 'SUCCESS', 'paymentId' => '22416035', 'currency' => 'TRY',
            'basketId' => $order->number, 'conversationId' => $order->number, 'paidPrice' => 1125.0, 'price' => 1125.0,
            'token' => 'tok-1', 'installment' => 1, 'cardFamily' => 'Bonus', 'cardAssociation' => 'MASTER_CARD', 'lastFourDigits' => '0008',
            'itemTransactions' => [
                ['itemId' => 'item-'.$order->items[0]->id, 'paymentTransactionId' => '24008934', 'price' => 585.0, 'paidPrice' => 585.0],
                ['itemId' => 'shipping', 'paymentTransactionId' => '24008935', 'price' => 90.0, 'paidPrice' => 90.0],
            ],
        ];
        // iyzico signs the values as they appear after JSON decoding (1125.0 becomes "1125").
        $payload['signature'] = self::sign(['SUCCESS', '22416035', 'TRY', $order->number, $order->number, '1125', '1125', 'tok-1']);
        $this->http->responses[] = $payload;

        $result = $this->gateway()->retrieve('tok-1');

        $this->assertTrue($result->successful);
        $this->assertTrue($result->signatureValid);
        $this->assertSame(112500, $result->paidPrice);
        $this->assertSame('24008934', $result->transactionIds['item-'.$order->items[0]->id]);
        $this->assertSame('24008935', $result->transactionIds['shipping']);
        $this->assertSame('0008', $result->lastFour);

        $payload['paidPrice'] = 1.0; // tampered amount, signature no longer matches
        $this->http->responses[] = $payload;
        $this->assertFalse($this->gateway()->retrieve('tok-1')->signatureValid);
    }

    public function test_retrieve_reports_failed_payments(): void
    {
        $this->http->responses[] = ['status' => 'failure', 'errorCode' => '10051', 'errorMessage' => 'Kart limiti yetersiz', 'conversationId' => 'PRZ-26-000001'];

        $result = $this->gateway()->retrieve('tok-1');

        $this->assertFalse($result->successful);
        $this->assertSame('Kart limiti yetersiz', $result->errorMessage);
    }

    public function test_webhook_signature_matches_iyzico_reference_example(): void
    {
        // Values from the signature example shipped with the iyzico PHP SDK.
        $gateway = new IyzicoGateway('key', 'sandbox-UuLnD7wZa0a3DmytfIrwHQqAMfqCNJrs', 'https://sandbox-api.iyzipay.com');
        $payload = [
            'paymentConversationId' => 'conversationId', 'merchantId' => 3382172, 'token' => 'e828c62d-fd12-4b1a-ad5a-380f15fe6596',
            'status' => 'SUCCESS', 'iyziReferenceCode' => '77e1635c-6b39-475a-8b7b-ca15ad0cd4ac', 'iyziEventType' => 'CHECKOUT_FORM_AUTH',
            'iyziEventTime' => 1721825036103, 'iyziPaymentId' => 22483975,
        ];

        $this->assertTrue($gateway->verifyWebhook($payload, '4031c575763486a2d2bf3f7ace0baebfcd6bed65393ab4df3fe80c00d5fd6878'));
        $this->assertFalse($gateway->verifyWebhook($payload, str_repeat('0', 64)));
        $this->assertFalse($gateway->verifyWebhook($payload, null));
    }
}

class FakeIyzicoHttp implements HttpClient
{
    public array $requests = [];

    public array $responses = [];

    public function get($url) {}

    public function getV2($url, $header) {}

    public function post($url, $header, $content)
    {
        $this->requests[] = ['url' => $url, 'header' => $header, 'content' => $content];

        return json_encode(array_shift($this->responses));
    }

    public function put($url, $header, $content) {}

    public function delete($url, $header, $content = null) {}
}
