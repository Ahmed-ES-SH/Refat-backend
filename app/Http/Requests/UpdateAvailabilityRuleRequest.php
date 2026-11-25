<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

/**
 * Shares the window checks with the store request: a patch is validated as
 * the full window it will become, not only the fields that were sent.
 */
class UpdateAvailabilityRuleRequest extends StoreAvailabilityRuleRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'weekday' => ['sometimes', 'integer', 'between:0,6'],
            'start_time' => ['sometimes', 'date_format:H:i:s'],
            'end_time' => ['sometimes', 'date_format:H:i:s'],
            'slot_minutes' => ['sometimes', 'integer', 'min:10', 'max:120'],
        ];
    }

    /**
     * The rule being patched is the base; sent fields win over it.
     *
     * @return array{doctor_id: int, weekday: int, start_time: string, end_time: string, slot_minutes: int}
     */
    protected function windowToCheck(Validator $validator): array
    {
        $rule = $this->route('rule');
        $data = $validator->getData();

        return [
            'doctor_id' => $rule->doctor_id,
            'weekday' => (int) ($data['weekday'] ?? $rule->weekday->value),
            'start_time' => $data['start_time'] ?? $rule->start_time,
            'end_time' => $data['end_time'] ?? $rule->end_time,
            'slot_minutes' => (int) ($data['slot_minutes'] ?? $rule->slot_minutes),
        ];
    }

    /**
     * The rule itself must not overlap with the window it already has.
     */
    protected function ruleIdToExclude(): ?int
    {
        return $this->route('rule')->id;
    }
}
