<?php

/**
 * @file classes/user/UserInterest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserInterest
 *
 * @brief 
 */

namespace PKP\user;

use APP\facades\Repo;
use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use PKP\core\ArrayItemIterator;
use PKP\user\InterestEntryDAO;
use PKP\db\DAORegistry;

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
     * Compatibility function for including notes in grids.
     *
     * @deprecated 3.5. Use $model or $model->$field instead. Can be removed once the DataObject pattern is removed.
     */
    public function getData(?string $field): mixed
    {
        return $field ? $this->$field : $this;
    }

    /**
     * Scope a query to only include notes with a specific assoc type and assoc ID.
     */
    public function scopeWithUserId(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get a list of controlled vocabulary entry IDs (corresponding to interest keywords) 
     * attributed to a user
     */
    public static function getUserInterestIds(int $userId): array
    {
        $controlledVocab = Repo::controlledVocab()->build(
            static::CONTROLLED_VOCAB_INTEREST
        );

        return DB::table('controlled_vocab_entries AS cve')
            ->select(['cve.controlled_vocab_entry_id'])
            ->join(
                'user_interests AS ui', 
                fn (JoinClause $join) => $join
                    ->on('cve.controlled_vocab_entry_id', '=', 'ui.controlled_vocab_entry_id')
                    ->where('ui.user_id', $userId)
            )
            ->where('controlled_vocab_id', $controlledVocab->id)
            ->get()
            ->pluck('controlled_vocab_entry_id')
            ->toArray();
    }

    /**
     * Get a list of user IDs attributed to an interest
     */
    public static function getUserIdsByInterest(string $interest): array
    {
        return DB::table('user_interests AS ui')
            ->select('ui.user_id')
            ->join(
                'controlled_vocab_entry_settings AS cves',
                fn (JoinClause $join) => $join
                    ->on('cves.controlled_vocab_entry_id', '=', 'ui.controlled_vocab_entry_id')
                    ->where('cves.setting_name', STATIC::CONTROLLED_VOCAB_INTEREST)
                    ->where(DB::raw('LOWER(cves.setting_value)'), trim(strtolower($interest)))
            )
            ->get()
            ->pluck('user_id')
            ->toArray();
    }


    /**
     * Get all user's interests
     */
    public static function getAllInterests(?string $filter = null): object
    {
        $controlledVocab = Repo::controlledVocab()->build(
            static::CONTROLLED_VOCAB_INTEREST
        );

        $interestEntryDao = DAORegistry::getDAO('InterestEntryDAO'); /** @var InterestEntryDAO $interestEntryDao */
        $iterator = $interestEntryDao->getByControlledVocabId($controlledVocab->id, null, $filter);

        // Sort by name.
        $interests = $iterator->toArray();
        usort($interests, function ($s1, $s2) {
            return strcmp($s1->getInterest(), $s2->getInterest());
        });

        // Turn back into an iterator.
        return new ArrayItemIterator($interests);
    }
}
