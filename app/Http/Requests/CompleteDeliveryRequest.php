<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Proof of delivery from the rider app (web form or API multipart upload).
 */
class CompleteDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'recipient_relation' => ['nullable', 'string', 'max:60'],
            'amount_collected' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['photo.required' => 'Take a photo of the parcel with the recipient.'];
    }
}
