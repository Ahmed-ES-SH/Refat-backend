<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilityRuleResource extends JsonResource
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
            'doctor_id' => $this->doctor_id,
            'weekday' => $this->weekday->value,
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'slot_minutes' => $this->slot_minutes,
            'slots_count' => $this->slotsCount(),
        ];
    }

    /**
     * How many slots the window yields. Validation guarantees the window
     * divides into whole slots, so the division is exact.
     */
    private function slotsCount(): int
    {
        $windowMinutes = (strtotime($this->end_time) - strtotime($this->start_time)) / 60;

        return (int) ($windowMinutes / $this->slot_minutes);
    }
}
