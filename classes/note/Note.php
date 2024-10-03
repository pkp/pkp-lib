<?php

/**
 * @file classes/note/Note.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Note
 *
 * @brief Class for Note.
 */

namespace PKP\note;

use APP\facades\Repo;
use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use PKP\db\DAO;

class Note extends Model
{
    use HasCamelCasing;

    public const NOTE_ORDER_DATE_CREATED = 1;
    public const NOTE_ORDER_ID = 2;

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

    protected $table = 'notes';
    protected $primaryKey = 'note_id';

    protected $fillable = [
        'assocType', 'assocId', 'userId',
        'dateCreated', 'dateModified',
        'title', 'contents'
    ];

    protected function casts(): array
    {
        return [
            'assocType' => 'int',
            'assocId' => 'int',
            'userId' => 'int',
            'dateCreated' => 'datetime',
            'dateModified' => 'datetime'
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
     * Accessor for user. Can be replaced with relationship once User is converted to an Eloquent Model.
     */
    protected function user(): Attribute
    {
        return Attribute::make(
            get: function () {
                return Repo::user()->get($this->userId, true);
            },
        );
    }

    /**
     * Compatibility function for including note IDs in grids.
     *
     * @deprecated 3.5 Use $model->id instead. Can be removed once the DataObject pattern is removed.
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
    public function getData(?string $field)
    {
        return $field ? $this->$field : $this;
    }

    // Scopes

    /**
     * Scope a query to only include notes with a specific user ID.
     */
    public function scopeWithUserId(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include notes with a specific assoc type and assoc ID.
     */
    public function scopeWithAssoc(Builder $query, int $assocType, int $assocId): Builder
    {
        return $query->where('assoc_type', $assocType)
                     ->where('assoc_id', $assocId);
    }

    /**
     * Scope a query to only include notes with a specific type.
     */
    public function scopeWithType(Builder $query, int $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include notes with a specific type.
     */
    public function scopeWithContents(Builder $query, string $contents): Builder
    {
        return $query->where('contents', $contents);
    }

    /**
     * Scope a query to a specific sort order.
     */
    public function scopeWithSort(Builder $query, int $orderBy = self::NOTE_ORDER_DATE_CREATED, int $sortDirection = DAO::SORT_DIRECTION_DESC): Builder
    {
        // Sanitize sort ordering
        $orderSanitized = match ($orderBy) {
            self::NOTE_ORDER_ID => 'note_id',
            self::NOTE_ORDER_DATE_CREATED => 'date_created',
        };

        $directionSanitized = match ($sortDirection) {
            DAO::SORT_DIRECTION_ASC => 'ASC',
            DAO::SORT_DIRECTION_DESC => 'DESC',
        };

        return $query->orderBy($orderSanitized, $directionSanitized);
    }
}
