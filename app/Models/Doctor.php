<?php

namespace App\Models;

use App\Enums\Specialty;
use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'center_id',
    'specialty',
    'name',
    'bio',
    'photo_path',
    'is_active',
])]
class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'specialty' => Specialty::class,
        ];
    }

    /**
     * The center this doctor belongs to.
     *
     * @return BelongsTo<Center, $this>
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /**
     * Weekly recurring availability rules for this doctor.
     *
     * @return HasMany<AvailabilityRule, $this>
     */
    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    /**
     * Days off (exceptions) for this doctor.
     *
     * @return HasMany<DayOff, $this>
     */
    public function daysOff(): HasMany
    {
        return $this->hasMany(DayOff::class);
    }
}
