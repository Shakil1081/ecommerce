<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TrackOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:60'],
            'phone' => ['required', 'string', 'max:25'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_number.required' => 'Please enter your order number.',
            'phone.required' => 'Please enter the mobile phone number used during checkout.',
        ];
    }
}
