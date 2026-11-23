<?php

namespace App\Models;

use Database\Factories\CenterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'description',
    'logo_path',
    'phone',
    'email',
    'country_code',
    'city',
    'address',
    'latitude',
    'longitude',
    'license_no',
    'timezone',
    'status',
    'approved_at',
])]
class Center extends Model
{
    /** @use HasFactory<CenterFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Staff accounts that manage this center.
     *
     * @return HasMany<User, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
