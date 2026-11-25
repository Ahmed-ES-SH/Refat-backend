<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDayOffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $doctor = $this->route('doctor');

        return [
            'date' => [
                'required',
                'date',
                'after_or_equal:today',
                Rule::unique('days_off')->where('doctor_id', $doctor->id),
            ],
            'reason' => ['nullable', 'string', 'max:150'],
        ];
    }
}
