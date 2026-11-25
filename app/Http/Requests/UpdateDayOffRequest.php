<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDayOffRequest extends FormRequest
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
        $dayOff = $this->route('dayOff');

        return [
            'date' => [
                'sometimes',
                'date',
                'after_or_equal:today',
                Rule::unique('days_off')
                    ->where('doctor_id', $dayOff->doctor_id)
                    ->ignore($dayOff->id),
            ],
            'reason' => ['nullable', 'string', 'max:150'],
        ];
    }
}
