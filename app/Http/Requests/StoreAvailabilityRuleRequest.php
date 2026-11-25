<?php

namespace App\Http\Requests;

use App\Models\AvailabilityRule;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAvailabilityRuleRequest extends FormRequest
{
    /**
     * Mirrors the `availability_rules.slot_minutes` column default, used when
     * the caller does not send a slot length.
     */
    private const DEFAULT_SLOT_MINUTES = 30;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    /**
     * The API speaks `H:i` but the column stores `H:i:s`, so times are padded
     * before validation. Values that do not parse are left untouched for the
     * `date_format` rules to reject.
     */
    protected function prepareForValidation(): void
    {
        $updates = [];

        foreach (['start_time', 'end_time'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $updates[$field] = $this->normalizeTime($value);
            }
        }

        if ($updates !== []) {
            $this->merge($updates);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'end_time' => ['required', 'date_format:H:i:s'],
            'slot_minutes' => ['nullable', 'integer', 'min:10', 'max:120'],
        ];
    }

    /**
     * Window checks that need several fields at once run after the rules.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectUnusableWindow($validator);
            },
        ];
    }

    /**
     * The full window to check: submitted fields merged with what is already
     * stored, so a partial update is validated as the window it will become.
     *
     * @return array{doctor_id: int, weekday: int, start_time: string, end_time: string, slot_minutes: int}
     */
    protected function windowToCheck(Validator $validator): array
    {
        $data = $validator->getData();
        $doctor = $this->route('doctor');

        return [
            'doctor_id' => $doctor->id,
            'weekday' => (int) $data['weekday'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'slot_minutes' => (int) ($data['slot_minutes'] ?? self::DEFAULT_SLOT_MINUTES),
        ];
    }

    /**
     * A new rule has no previous version to ignore in the overlap check.
     */
    protected function ruleIdToExclude(): ?int
    {
        return null;
    }

    /**
     * Reject windows that end at or before they start, do not divide into
     * whole slots, or overlap another window of the same doctor and weekday.
     */
    private function rejectUnusableWindow(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['weekday', 'start_time', 'end_time', 'slot_minutes'])) {
            return;
        }

        $window = $this->windowToCheck($validator);
        $windowMinutes = self::minutesSinceMidnight($window['end_time'])
            - self::minutesSinceMidnight($window['start_time']);

        if ($windowMinutes <= 0) {
            $validator->errors()->add('end_time', __('availability.end_after_start'));

            return;
        }

        if ($windowMinutes % $window['slot_minutes'] !== 0) {
            $validator->errors()->add('end_time', __('availability.window_not_aligned', [
                'slot_minutes' => $window['slot_minutes'],
            ]));
        }

        if ($this->windowOverlaps($window)) {
            $validator->errors()->add('start_time', __('availability.window_overlap'));
        }
    }

    /**
     * @param  array{doctor_id: int, weekday: int, start_time: string, end_time: string, slot_minutes: int}  $window
     */
    private function windowOverlaps(array $window): bool
    {
        return AvailabilityRule::query()
            ->where('doctor_id', $window['doctor_id'])
            ->where('weekday', $window['weekday'])
            ->where('start_time', '<', $window['end_time'])
            ->where('end_time', '>', $window['start_time'])
            ->when($this->ruleIdToExclude(), fn (Builder $query, int $ruleId) => $query->whereKeyNot($ruleId))
            ->exists();
    }

    /**
     * Round-trips the value the same way `date_format` does, so only real
     * times are padded and nonsense reaches the validator unchanged.
     */
    private function normalizeTime(string $value): string
    {
        foreach (['H:i:s', 'H:i'] as $format) {
            $time = DateTime::createFromFormat('!'.$format, $value);

            if ($time !== false && $time->format($format) === $value) {
                return $time->format('H:i:s');
            }
        }

        return $value;
    }

    private static function minutesSinceMidnight(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return (int) $hours * 60 + (int) $minutes;
    }
}
