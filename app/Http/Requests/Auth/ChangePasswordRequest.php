<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;
use App\Rules\StrongPassword;
use App\Rules\NotInPasswordHistory;

class ChangePasswordRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $passwordMinLength = config('voip.security.password.min_length', 8);
        $historyCount = config('voip.security.password.history_count', 5);
        
        return [
            'current_password' => [
                'required',
                'string',
                'max:255'
            ],
            'password' => [
                'required',
                'string',
                'min:' . $passwordMinLength,
                'max:255',
                'confirmed',
                'different:current_password',
                new StrongPassword($passwordMinLength),
                new NotInPasswordHistory($this->user()->id, $historyCount),
            ],
            'password_confirmation' => [
                'required',
                'string',
                'min:' . $passwordMinLength,
                'max:255'
            ],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'password.different' => 'The new password must be different from the current password.',
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ]);
    }
}