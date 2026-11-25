<?php

namespace App\Http\Resources;

use App\Models\Center;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Center
 */
class CenterResource extends JsonResource
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
            'description' => $this->description,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logo_path ? asset('storage/'.$this->logo_path) : null,
            'phone' => $this->phone,
            'email' => $this->email,
            'country_code' => $this->country_code,
            'city' => $this->city,
            'address' => $this->address,
            'latitude' => $this->latitude ? (float) $this->latitude : null,
            'longitude' => $this->longitude ? (float) $this->longitude : null,
            'license_no' => $this->license_no,
            'timezone' => $this->timezone,
            'status' => $this->status,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'staff' => $this->whenLoaded('staff', function () {
                return $this->staff->map(fn ($staff) => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'role' => $staff->role->value,
                    'status' => $staff->status->value,
                ]);
            }),
        ];
    }
}
