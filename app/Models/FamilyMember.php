<?php

namespace App\Models;

use App\Enums\FamilyRelation;
use App\Enums\Gender;
use Database\Factories\FamilyMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'relation', 'date_of_birth', 'gender'])]
class FamilyMember extends Model
{
    /** @use HasFactory<FamilyMemberFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relation' => FamilyRelation::class,
            'gender' => Gender::class,
            'date_of_birth' => 'date',
        ];
    }

    /**
     * The patient who owns this dependant.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
