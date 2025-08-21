<?php

/**
 * @file classes/editorialTask/EditorialTask.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialTask
 *
 * @brief Class for editorial tasks and discussions.
 */

namespace PKP\editorialTask;

use APP\facades\Repo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PKP\core\PKPApplication;
use PKP\core\traits\ModelWithSettings;
use PKP\note\Note;
use PKP\notification\Notification;

class EditorialTask extends Model
{
    use ModelWithSettings;

    // Allow filling and saving related model through 'participants' Model attribute
    public const ATTRIBUTE_PARTICIPANTS = 'participants';

    /**
     * @var array<Participant> $taskParticipants
     */
    protected ?array $taskParticipants = null;

    protected $table = 'edit_tasks';
    protected $primaryKey = 'edit_task_id';

    protected $fillable = [
        'assocType', 'assocId', 'stageId', 'seq',
        'createdAt', 'updatedAt', 'closed', 'dateDue',
        'createdBy', 'type', 'status', 'dateStarted',
        'dateClosed', 'title',
    ];

    protected $casts = [
        'assocType' => 'int',
        'assocId' => 'int',
        'stageId' => 'int',
        'seq' => 'float',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'closed' => 'boolean',
        'dateDue' => 'datetime:Y-m-d',
        'createdBy' => 'int',
        'type' => 'int',
        'dateStarted' => 'datetime:Y-m-d',
        'dateClosed' => 'datetime:Y-m-d',
        'title' => 'string',
    ];

    protected static function booted(): void
    {
        // Delete connected model data when an Editorial Task is deleted.
        static::deleted(function (EditorialTask $task) {
            Note::withAssoc(PKPApplication::ASSOC_TYPE_QUERY, $task->id)->delete();
            Notification::withAssoc(PKPApplication::ASSOC_TYPE_QUERY, $task->id)->delete();
        });
    }

    /**
     * @inheritDoc
     */
    public function getSettingsTable(): string
    {
        return 'edit_task_settings';
    }

    /**
     * @inheritDoc
     */
    public static function getSchemaName(): ?string
    {
        return null;
    }

    /**
     * Override Eloquent Model's method to accept participant IDs as an attribute
     */
    public function fill(array $attributes)
    {
        if (!isset($attributes[self::ATTRIBUTE_PARTICIPANTS])) {
            return parent::fill($attributes);
        }

        $participants = $attributes[self::ATTRIBUTE_PARTICIPANTS];
        foreach ($participants as $participant) {
            $participant['editTaskId'] = $this->id;
            $this->taskParticipants[$participant['userId']] = new Participant($participant);
        }

        unset($attributes[self::ATTRIBUTE_PARTICIPANTS]);
        return parent::fill($attributes);
    }

    /**
     * Override Eloquent Model's method to save participants associated with the Tasks and Discussion
     */
    public function save(array $options = []): bool
    {
        $exists = $this->exists;

        $success = parent::save($options);
        if (!$success) {
            return false;
        }

        // If it's newly created task, just save participants
        if (!$exists && is_array($this->taskParticipants)) {
            $this->participants()->saveMany($this->taskParticipants);
            return $success;
        }

        // If the task is being updated, we need to determine what to do with participants

        // Participants weren't passed to the model, so we don't need to do anything
        if (!is_array($this->taskParticipants)) {
            return $success;
        }

        // Participants were passed as an empty array, so we need to remove them
        if (empty($this->taskParticipants)) {
            $this->participants()->delete();
            return $success;
        }

        // Participants were passed, so we need to determine which particular ones should be removed, updated or created
        $oldParticipantIds = $this->participants()->pluck('user_id')->all();
        $newParticipantIds = array_keys($this->taskParticipants);
        $deleteParticipantIds = array_diff($oldParticipantIds, $newParticipantIds);

        $this->participants()->whereIn('user_id', $deleteParticipantIds)->delete(); // Remove non-existing participants

        // Update existing participants and create new ones in one operation; note that this will cause the sequence number to increment even on update
        Participant::upsert(
            Arr::map($this->taskParticipants, function (Participant $participant) {
                $data = [];
                foreach ($participant->getAttributes() as $key => $value) {
                    $data[Str::snake($key)] = $value;
                }
                return $data;
            }),
            uniqueBy: [
                'edit_task_id', 'user_id'
            ],
            update: Arr::map(
                (new Participant())->getFillable(),
                fn (string $fillable) => Str::snake($fillable)
            )
        );

        return $success;
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
     * Accessor for users. Can be replaced with relationship once User is converted to an Eloquent Model.
     */
    protected function users(): Attribute
    {
        return Attribute::make(
            get: function () {
                $userIds = $this->participants()
                    ->pluck('user_id')
                    ->all();
                return Repo::user()->getCollector()->filterByUserIds($userIds)->getMany();
            },
        );
    }

    /**
     * Relationship to Query Participants. Can be replaced with Many-to-Many relationship once
     * User is converted to an Eloquent Model.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class, 'edit_task_id', 'edit_task_id');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'assoc');
    }

    // Scopes

    /**
     * Scope a query to only include queries with a specific assoc type and assoc ID.
     */
    public function scopeWithAssoc(Builder $query, int $assocType, int $assocId): Builder
    {
        return $query->where('assoc_type', $assocType)
            ->where('assoc_id', $assocId);
    }

    /**
     * Scope a query to only include queries with a specific stage ID.
     */
    public function scopeWithStageId(Builder $query, int $stageId): Builder
    {
        return $query->where('stage_id', $stageId);
    }

    /**
     * Scope a query to only include queries with a specific closed status.
     */
    public function scopeWithClosed(Builder $query, bool $closed): Builder
    {
        return $query->where('closed', $closed);
    }

    /**
     * Scope a query to only include queries with specific user IDs.
     */
    public function scopeWithParticipantIds($query, array $userIds)
    {
        return $query->whereHas('participants', function ($q) use ($userIds) {
            $q->whereIn('user_id', $userIds);
        });
    }

    /**
     * Filter a query by IDs of associated entities, e.g., submissions.
     */
    public function scopeWithAssocIds(Builder $query, array $assocIds): Builder
    {
        return $query->whereIn('assoc_id', $assocIds);
    }

    /**
     * @param int $assocType one of the PKPApplication::ASSOC_TYPE constants.
     */
    public function scopeWithAssocType(Builder $query, int $assocType): Builder
    {
        return $query->where('assoc_type', $assocType);
    }
}
