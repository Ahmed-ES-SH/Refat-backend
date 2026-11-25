<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'specialty' => $this->specialty?->value,
            'bio' => $this->bio,
            'photo_path' => $this->photo_path,
            'is_active' => $this->is_active,
            'center' => $this->whenLoaded('center', function ($center) {
                return [
                    'id' => $center->id,
                    'name' => $center->name,
                ];
            }),
            'availability_rules' => $this->whenLoaded('availabilityRules', function ($rules) {
                return $rules->map(function ($rule) {
                    return [
                        'id' => $rule->id,
                        'weekday' => $rule->weekday->value,
                        'start_time' => $rule->start_time,
                        'end_time' => $rule->end_time,
                        'slot_minutes' => $rule->slot_minutes,
                    ];
                });
            }),
            'days_off' => $this->whenLoaded('daysOff', function ($daysOff) {
                return $daysOff->map(function ($dayOff) {
                    return [
                        'id' => $dayOff->id,
                        'date' => $dayOff->date,
                        'reason' => $dayOff->reason,
                    ];
                });
            }),
        ];
    }
}
