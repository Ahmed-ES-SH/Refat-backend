<?php

namespace App\Http\Requests\Family;

use App\Enums\FamilyRelation;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFamilyMemberRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'relation' => ['required', Rule::enum(FamilyRelation::class)],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::enum(Gender::class)],
        ];
    }
}
