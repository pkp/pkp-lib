<?php

/**
 * @file classes/user/interest/UserInterest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserInterest
 *
 * @brief UserInterest model calss
 */

namespace PKP\user\interest;

use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;


class UserInterest extends Model
{
    use HasCamelCasing;

    public const CONTROLLED_VOCAB_INTEREST = 'interest';

    protected $table = 'user_interests';
    protected $primaryKey = 'user_interest_id';

    protected $guarded = [
        'user_interest_id',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'controlled_vocab_entry_id' => 'integer',
        ];
    }

    /**
     * Accessor and Mutator for primary key => id
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn($value) => [$this->primaryKey => $value],
        );
    }

    /**
     * Compatibility function for including note IDs in grids.
     *
     * @deprecated 3.5.0 Use $model->id instead. Can be removed once the DataObject pattern is removed.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Scope a query to only include notes with a specific assoc type and assoc ID.
     */
    public function scopeWithUserId(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
