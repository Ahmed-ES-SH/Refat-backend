<?php

namespace App\Models;

use Database\Factories\DayOffFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A full-day exception for a doctor.
 *
 * `date` is a calendar date in the center's timezone, not a moment in time,
 * so it stays a `Y-m-d` string: a `date` cast would serialize it to a
 * datetime and break the `(doctor_id, date)` uniqueness check.
 */
#[Fillable([
    'doctor_id',
    'date',
    'reason',
])]
class DayOff extends Model
{
    /** @use HasFactory<DayOffFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'days_off';

    /**
     * The doctor this day off belongs to.
     *
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
