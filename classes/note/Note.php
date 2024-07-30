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

    protected $table = 'notes';
    protected $primaryKey = 'note_id';
    public $timestamps = false;

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
     * Return the user of the note's author.
     *
     * @return \PKP\user\User
     */
    public function getUser()
    {
        return Repo::user()->get($this->userId, true);
    }

    /**
     * Compatibility function for including note IDs in grids.
     *
     * @deprecated
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Compatibility function for including notes in grids.
     *
     * @deprecated
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
        return $query->where('userId', $userId);
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
    public function scopeWithSort(Builder $query, int $orderBy = NOTE_ORDER_DATE_CREATED, int $sortDirection = DAO::SORT_DIRECTION_DESC): Builder
    {
        // Sanitize sort ordering
        $orderSanitized = match ($orderBy) {
            self::NOTE_ORDER_ID => 'note_id',
            default => 'date_created',
        };

        $directionSanitized = match ($sortDirection) {
            DAO::SORT_DIRECTION_ASC => 'ASC',
            default => 'DESC',
        };

        return $query->orderBy($orderSanitized, $directionSanitized);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\note\Note', '\Note');
}
