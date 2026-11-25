<?php

namespace App\Http\Resources;

use App\Models\Center;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Center
 */
class CenterDirectoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // The directory only lists active doctors. The query filters them
        // already; the check is repeated here so the resource stays correct
        // whatever loaded the relation.
        $specialties = $this->whenLoaded('doctors', function () {
            return $this->doctors
                ->where('is_active', true)
                ->pluck('specialty')
                ->unique()
                ->values()
                ->map(fn ($specialty) => $specialty->value)
                ->toArray();
        }, []);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logo_path ? asset('storage/'.$this->logo_path) : null,
            'phone' => $this->phone,
            'email' => $this->email,
            'city' => $this->city,
            'address' => $this->address,
            'latitude' => $this->latitude ? (float) $this->latitude : null,
            'longitude' => $this->longitude ? (float) $this->longitude : null,
            'specialties' => $specialties,
        ];
    }
}
