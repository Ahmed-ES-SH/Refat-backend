<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCenterProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Lengths mirror the `centers` migration exactly: MySQL runs in strict mode,
     * so a value wider than its column is a 500, not a 422.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // `name` is deliberately absent: the center name is fixed at
            // registration and approved by an admin, so staff cannot rename it.
            'description' => ['nullable', 'string'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'email' => ['sometimes', 'email', 'max:190'],
            'city' => ['sometimes', 'string', 'max:80'],
            'address' => ['sometimes', 'string', 'max:255'],
            'country_code' => ['sometimes', 'string', 'size:2'],
            // Slot computation converts center-local times to UTC with this
            // value, so it must be a real IANA identifier, not any string.
            'timezone' => ['sometimes', 'string', 'max:40', 'timezone'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'logo' => ['nullable', 'file', 'mimes:jpeg,png,webp', 'max:2048'],
        ];
    }
}
