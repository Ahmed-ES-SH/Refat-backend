<?php

namespace App\Http\Requests;

use App\Support\PasswordPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CenterRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The payload carries two entities: the center and its first
            // staff account.
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:190'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'timezone' => ['required', 'string', 'max:40', 'timezone'],
            'license_no' => ['required', 'string', 'max:60'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'staff_name' => ['required', 'string', 'max:120'],
            'staff_email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'staff_password' => ['required', 'string', PasswordPolicy::forStaff()],
        ];
    }
}
