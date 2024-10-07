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
use Illuminate\Database\Eloquent\Relations\HasMany;
use PKP\controlledVocab\ControlledVocabEntry;
use APP\facades\Repo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;

class UserInterest extends Model
{
    use HasCamelCasing;

    public const CONTROLLED_VOCAB_INTEREST = 'interest';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$table
     */
    protected $table = 'user_interests';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$primaryKey
     */
    protected $primaryKey = 'user_interest_id';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\GuardsAttributes::$guarded
     */
    protected $guarded = [
        'user_interest_id',
    ];

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasTimestamps::$timestamps
     */
    public $timestamps = false;

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasAttributes::casts
     */
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
        )->shouldCache();
    }

    /**
     * Accessor for user.
     * Should replace with relationship once User is converted to an Eloquent Model.
     */
    protected function user(): Attribute
    {
        return Attribute::make(
            get: fn () => Repo::user()->get($this->userId, true),
        )->shouldCache();
    }

    /**
     * Get all the controlled vocab entries for this user interest
     */
    public function controlledVocabEntries(): HasMany
    {
        return $this->hasMany(ControlledVocabEntry::class, 'controlled_vocab_entry_id', 'controlled_vocab_entry_id');
    }

    // TODO: Investigate if this is necessary anymore
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
     * Scope a query to only include interests with a specific user id
     */
    public function scopeWithUserId(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include interest with a specific controlled vocab entry id
     */
    public function scopeWithControlledVocabEntryId(Builder $query, int $controlledVocabEntryId): Builder
    {
        return $query->where('controlled_vocab_entry_id', $controlledVocabEntryId);
    }
}
