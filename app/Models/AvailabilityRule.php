<?php

namespace App\Models;

use App\Enums\Weekday;
use Database\Factories\AvailabilityRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $start_time The start of the window, `H:i:s` in the timezone of the center.
 * @property string $end_time The end of the window, `H:i:s` in the timezone of the center.
 */
#[Fillable([
    'doctor_id',
    'weekday',
    'start_time',
    'end_time',
    'slot_minutes',
])]
class AvailabilityRule extends Model
{
    /** @use HasFactory<AvailabilityRuleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * The times stay plain strings: they are `TIME` columns holding a wall
     * clock time, not a moment in time.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
            'slot_minutes' => 'integer',
        ];
    }

    /**
     * The doctor this availability rule belongs to.
     *
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
