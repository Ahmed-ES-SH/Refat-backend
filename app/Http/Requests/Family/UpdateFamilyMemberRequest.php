<?php

namespace App\Http\Requests\Family;

use App\Enums\FamilyRelation;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFamilyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'relation' => ['sometimes', 'required', Rule::enum(FamilyRelation::class)],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before:today'],
            'gender' => ['sometimes', 'required', Rule::enum(Gender::class)],
        ];
    }
}
