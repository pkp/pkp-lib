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
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPApplication;
use PKP\db\DAO;

class Note extends Model
{
    use HasCamelCasing;
    use SaveNoteWithFiles;

    public const NOTE_ORDER_DATE_CREATED = 1;
    public const NOTE_ORDER_ID = 2;

    public const CREATED_AT = 'date_created';
    public const UPDATED_AT = 'date_modified';

    protected $table = 'notes';
    protected $primaryKey = 'note_id';

    protected $fillable = [
        'assocType', 'assocId', 'userId',
        'dateCreated', 'dateModified',
        'title', 'contents', 'isHeadnote'
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

    protected static function booted(): void
    {
        static::deleted(function (Note $note) {
            DB::table(Repo::submissionFile()->dao->table)->where('assoc_type', '=', PKPApplication::ASSOC_TYPE_NOTE)
                ->where('assoc_id', '=', $note->id)
                ->delete();
        });
    }

    /**
     * Get the parent commentable (e.g., editorial task) model
     */
    public function assoc(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Accessor and Mutator for primary key => id
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn ($value) => [$this->primaryKey => $value],
        );
    }

    /**
     * Accessor for user. Can be replaced with relationship once User is converted to an Eloquent Model.
     */
    protected function user(): Attribute
    {
        return Attribute::make(
            get: function () {
                $userId = $this->userId;

                // system-created note. no user
                if (empty($userId)) {
                    return null;
                }

                return Repo::user()->get((int) $userId, true);
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
     * Scope a query to filter by assoc IDs.
     */
    public function scopeWithAssocIds(Builder $query, int $assocType, array $assocIds): Builder
    {
        return $query->where('assoc_type', $assocType)
            ->whereIn('assoc_id', $assocIds);
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

    /**
     * Override Eloquent Model's method to save participants associated with the Tasks and Discussion
     */
    public function save(array $options = []): bool
    {
        $success = parent::save($options);
        if (!$success) {
            return false;
        }

        // If attributes representing associated files weren't passed, don't do anything
        if (!is_array($this->temporaryFiles) && !is_array($this->submissionFiles)) {
            return $success;
        }

        $this->manageFiles();

        return $success;
    }

    /**
     * Override Eloquent Model's method to accept participant IDs as an attribute
     */
    public function fill(array $attributes)
    {
        if (isset($attributes[self::ATTRIBUTE_TEMPORARY_FILE_IDS])) {
            $attributes = $this->fillTemporaryFiles($attributes);
        }

        if (isset($attributes[self::ATTRIBUTE_SUBMISSION_FILE_IDS])) {
            $attributes = $this->fillSubmissionFiles($attributes);
        }

        return parent::fill($attributes);
    }
}
