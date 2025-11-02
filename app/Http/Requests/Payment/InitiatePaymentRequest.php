<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\BaseFormRequest;

class InitiatePaymentRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:10000',
                'regex:/^\d+(\.\d{1,2})?$/'
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                'in:USD,EUR,GBP,BTC,ETH,USDT'
            ],
            'gateway' => [
                'required',
                'string',
                'in:nowpayments,paypal'
            ],
            'payment_method' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_-]+$/'
            ],
            'return_url' => [
                'nullable',
                'url',
                'max:255',
                'regex:/^https?:\/\//'
            ],
            'cancel_url' => [
                'nullable',
                'url',
                'max:255',
                'regex:/^https?:\/\//'
            ],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'amount.regex' => 'The amount must be a valid decimal number with up to 2 decimal places.',
            'currency.regex' => 'The currency must be a valid 3-letter ISO code.',
            'payment_method.regex' => 'The payment method contains invalid characters.',
            'return_url.regex' => 'The return URL must use HTTP or HTTPS protocol.',
            'cancel_url.regex' => 'The cancel URL must use HTTP or HTTPS protocol.',
        ]);
    }
}