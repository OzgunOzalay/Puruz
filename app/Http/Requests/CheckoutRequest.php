<?php

namespace App\Http\Requests;

use App\Services\Cart\CartService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'billing_same' => $this->boolean('billing_same'),
            'marketing_consent' => $this->boolean('marketing_consent'),
            'customer_email' => trim((string) $this->input('customer_email')),
        ]);
    }

    public function rules(): array
    {
        $requiresShipping = app(CartService::class)->requiresShipping();
        $cities = array_keys(json_decode(file_get_contents(resource_path('data/turkiye-il-ilce.json')), true));
        $separateBilling = ! $requiresShipping || ! $this->boolean('billing_same');
        $corporate = $this->input('billing_type') === 'corporate';

        return [
            'customer_name' => ['required', 'string', 'min:3', 'max:120'],
            'customer_email' => ['required', 'email', 'max:190'],
            'customer_phone' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) {
                $digits = preg_replace('/\D+/', '', (string) $value);
                $digits = preg_replace('/^(90|0)/', '', $digits);
                if (! preg_match('/^5\d{9}$/', $digits)) {
                    $fail('Lütfen 05XX XXX XX XX biçiminde geçerli bir cep telefonu girin.');
                }
            }],
            'identity_number' => ['nullable', 'digits:11'],

            'shipping_name' => ['nullable', 'string', 'max:120'],
            'shipping_city' => [Rule::requiredIf($requiresShipping), 'nullable', Rule::in($cities)],
            'shipping_district' => [Rule::requiredIf($requiresShipping), 'nullable', 'string', 'max:60'],
            'shipping_address' => [Rule::requiredIf($requiresShipping), 'nullable', 'string', 'min:10', 'max:500'],
            'shipping_postal_code' => ['nullable', 'digits:5'],

            'billing_same' => ['boolean'],
            'billing_type' => ['required', Rule::in(['individual', 'corporate'])],
            'billing_name' => ['nullable', 'string', 'max:120'],
            'billing_city' => [Rule::requiredIf($separateBilling), 'nullable', Rule::in($cities)],
            'billing_district' => [Rule::requiredIf($separateBilling), 'nullable', 'string', 'max:60'],
            'billing_address' => [Rule::requiredIf($separateBilling), 'nullable', 'string', 'min:10', 'max:500'],
            'billing_company' => [Rule::requiredIf($corporate), 'nullable', 'string', 'max:190'],
            'billing_tax_office' => [Rule::requiredIf($corporate), 'nullable', 'string', 'max:100'],
            'billing_tax_number' => [Rule::requiredIf($corporate), 'nullable', 'digits_between:10,11'],

            'participants' => ['nullable', 'array'],
            'participants.*' => ['array'],
            'participants.*.*' => ['nullable', 'string', 'max:120'],
            'customer_note' => ['nullable', 'string', 'max:1000'],

            'accept_terms' => ['accepted'],
            'accept_kvkk' => ['accepted'],
            'marketing_consent' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_name' => 'ad soyad',
            'customer_email' => 'e-posta',
            'customer_phone' => 'telefon',
            'identity_number' => 'T.C. kimlik no',
            'shipping_city' => 'il',
            'shipping_district' => 'ilçe',
            'shipping_address' => 'adres',
            'shipping_postal_code' => 'posta kodu',
            'billing_city' => 'fatura ili',
            'billing_district' => 'fatura ilçesi',
            'billing_address' => 'fatura adresi',
            'billing_company' => 'firma unvanı',
            'billing_tax_office' => 'vergi dairesi',
            'billing_tax_number' => 'vergi numarası',
            'customer_note' => 'sipariş notu',
        ];
    }

    public function messages(): array
    {
        return [
            'accept_terms.accepted' => 'Devam etmek için Ön Bilgilendirme Formu ve Mesafeli Satış Sözleşmesi’ni onaylamanız gerekiyor.',
            'accept_kvkk.accepted' => 'Devam etmek için KVKK Aydınlatma Metni’ni okuduğunuzu onaylamanız gerekiyor.',
        ];
    }
}
