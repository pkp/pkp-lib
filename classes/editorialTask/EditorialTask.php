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

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PKP\core\PKPApplication;
use PKP\core\traits\ModelWithSettings;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileDAO;
use PKP\note\Note;
use PKP\notification\Notification;
use PKP\submissionFile\SubmissionFile;

class EditorialTask extends Model
{
    use ModelWithSettings;

    // Allow filling and saving related model through the 'participants' Model attribute
    public const ATTRIBUTE_PARTICIPANTS = 'participants';

    // Allow filling the headnote together with the task through the 'description' Model attribute
    public const ATTRIBUTE_HEADNOTE = 'description';

    // Allow filling temporary and submission file IDs to upload them and associate with the task
    public const ATTRIBUTE_TEMPORARY_FILE_IDS = 'temporaryFileIds';
    public const ATTRIBUTE_SUBMISSION_FILE_IDS = 'submissionFileIds';

    // Order directions
    public const ORDER_DIR_ASC = 'asc';
    public const ORDER_DIR_DESC = 'desc';

    // Order options
    public const ORDERBY_DATE_CREATED = 'dateCreated';
    public const ORDERBY_DATE_DUE = 'dateDue';
    public const ORDERBY_DATE_STARTED = 'dateStarted';

    /**
     * @var array<Participant> $taskParticipants
     */
    protected ?array $taskParticipants = null;

    /**
     * @var Note|null The note associated with the task, used as a headnote/description
     */
    protected ?Note $headnote = null;

    /**
     * @var ?array<TemporaryFile> Temporary files ID to be uploaded and associated with the task
     */
    protected ?array $temporaryFiles = null;

    /**
     * @var ?array<SubmissionFile> Submission file IDs to be associated with the task
     */
    protected ?array $submissionFiles = null;

    protected $table = 'edit_tasks';
    protected $primaryKey = 'edit_task_id';

    protected $fillable = [
        'assocType', 'assocId', 'stageId', 'seq',
        'createdAt', 'updatedAt', 'closed', 'dateDue',
        'createdBy', 'type', 'status', 'dateStarted',
        'dateClosed', 'title', 'startedBy'
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
        'startedBy' => 'int',
        'type' => 'int',
        'dateStarted' => 'datetime',
        'dateClosed' => 'datetime',
        'title' => 'string',
    ];

    protected static function booted(): void
    {
        // Delete connected model data when an Editorial Task is deleted.
        static::deleted(function (EditorialTask $task) {
            Note::withAssoc(PKPApplication::ASSOC_TYPE_QUERY, $task->id)->delete();
            Notification::withAssoc(PKPApplication::ASSOC_TYPE_QUERY, $task->id)->delete();
            DB::table(Repo::submissionFile()->dao->table)->where('assoc_type', '=', PKPApplication::ASSOC_TYPE_QUERY)
                ->where('assoc_id', '=', $task->id)
                ->delete();
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
        if (isset($attributes[self::ATTRIBUTE_HEADNOTE])) {
            $attributes = $this->fillHeadnote($attributes);
        }

        if (isset($attributes[self::ATTRIBUTE_PARTICIPANTS])) {
            $attributes = $this->fillParticipants($attributes);
        }

        if (isset($attributes[self::ATTRIBUTE_TEMPORARY_FILE_IDS])) {
            $attributes = $this->fillTemporaryFiles($attributes);
        }

        if (isset($attributes[self::ATTRIBUTE_SUBMISSION_FILE_IDS])) {
            $attributes = $this->fillSubmissionFiles($attributes);
        }

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

        $this->saveParticipants($exists);
        $this->saveHeadnote();

        // If attributes representing associated files weren't passed, don't do anything
        if (!is_array($this->temporaryFiles) && !is_array($this->submissionFiles)) {
            return $success;
        }

        // Current associated submission file IDs
        $existingFiles = Repo::submissionFile()->getCollector()
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_QUERY, [$this->id])
            ->getMany()
            ->toArray();

        $this->saveTemporaryFiles();
        $attachedFileIds = $this->attachSubmissionFiles($existingFiles);

        // Remove previously associated submission files, including situation when any of temporary or submission files attribute was passed as empty array
        foreach ($existingFiles as $existingFile) {
            if (in_array($existingFile->getId(), $attachedFileIds)) {
                continue;
            }
            // Using Repo method to ensure related files are also removed
            Repo::submissionFile()->delete($existingFile);
        }

        return $success;
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
    public function scopeWithOpen(Builder $query): Builder
    {
        return $query->whereNull('date_closed');
    }

    /**
     * Scope a query to order results by a specific date field.
     *
     * @param string $orderBy One of the ORDERBY_* constants.
     * @param string $direction One of the ORDER_DIR_* constants.
     *
     * @throws Exception
     */
    public function scopeOrderByDate(Builder $query, string $orderBy, string $direction = self::ORDER_DIR_ASC): Builder
    {
        return match ($orderBy) {
            self::ORDERBY_DATE_CREATED => $query->orderBy('created_at', $direction),
            self::ORDERBY_DATE_STARTED => $query->orderBy('date_started', $direction),
            self::ORDERBY_DATE_DUE => $query->orderBy('date_due', $direction),
            default => throw new Exception('Invalid order by option, please use one of the EditorialTask::ORDERBY_* constants.'),
        };
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

    /**
     * Override Laravel magic getter to provide access to participants and headnote as model attributes when they are set.
     */
    public function __get($key)
    {
        if (!in_array($key, [self::ATTRIBUTE_PARTICIPANTS, self::ATTRIBUTE_HEADNOTE, 'notes'])) {
            return parent::__get($key);
        }

        // If new participants are set before model is saved, return them
        if ($key == self::ATTRIBUTE_PARTICIPANTS && isset($this->taskParticipants)) {
            return collect($this->taskParticipants);

            // Allow accessing the headnote as an attribute
        } elseif ($key == self::ATTRIBUTE_HEADNOTE && isset($this->headnote)) {
            return $this->headnote;

            // If headnote is set before the model is saved, return it together with other notes. This scenario could happen during task editing
        } elseif ($key == 'notes' && isset($this->headnote)) {
            $notes = $this->getAttribute($key);
            if ($notes && $notes->isNotEmpty()) {
                return $notes->map(function ($note) {
                    if ($note->isHeadnote) {
                        return $this->headnote;
                    }
                    return $note;
                });
            } else {
                return collect([$this->headnote]);
            }
        } else {
            return $this->getAttribute($key);
        }
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
        )->shouldCache();
    }

    /**
     * Fill headnote when corresponding attribute is passed to the model or leave the description empty
     * Headnote should be always created together with the task/discussion
     */
    protected function fillHeadnote(array $attributes): array
    {
        if (isset($attributes[self::ATTRIBUTE_HEADNOTE])) {
            $this->headnote = new Note([
                'assocType' => PKPApplication::ASSOC_TYPE_QUERY,
                'assocId' => $this->id,
                'userId' => $attributes['createdBy'] ?? $this->createdBy,
                'contents' => $attributes[self::ATTRIBUTE_HEADNOTE],
                'isHeadnote' => true,
            ]);

            unset($attributes[self::ATTRIBUTE_HEADNOTE]);
        }

        return $attributes;
    }

    /**
     * Fill participants when corresponding attribute is passed to the model
     */
    protected function fillParticipants(array $attributes): array
    {
        if (isset($attributes[self::ATTRIBUTE_PARTICIPANTS])) {
            $participants = $attributes[self::ATTRIBUTE_PARTICIPANTS];
            foreach ($participants as $participant) {
                $participant['editTaskId'] = $this->id;
                $this->taskParticipants[$participant['userId']] = new Participant($participant);
            }
            unset($attributes[self::ATTRIBUTE_PARTICIPANTS]);
        }
        return $attributes;
    }

    /**
     * Fill temporary files by their IDs when corresponding attribute is passed to the model
     */
    protected function fillTemporaryFiles(array $attributes): array
    {
        $temporaryFileIds = $attributes[self::ATTRIBUTE_TEMPORARY_FILE_IDS];
        $dao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $dao */
        $this->temporaryFiles = $dao->getTemporaryFiles($temporaryFileIds)->toArray();
        unset($attributes[self::ATTRIBUTE_TEMPORARY_FILE_IDS]);

        return $attributes;
    }

    /**
     * Fill submission files by their IDs when corresponding attribute is passed to the model
     */
    protected function fillSubmissionFiles(array $attributes): array
    {
        $submissionFileIds = $attributes[self::ATTRIBUTE_SUBMISSION_FILE_IDS];
        $this->submissionFiles = Repo::submissionFile()->getCollector()
            ->filterByAssoc($this->assocType, $this->assocId)
            ->filterBySubmissionFileIds($submissionFileIds)
            ->getMany()
            ->toArray();
        unset($attributes[self::ATTRIBUTE_SUBMISSION_FILE_IDS]);

        return $attributes;
    }


    /**
     * Save participants into a separate table.
     *
     * @param bool $exists Whether the task model already exists in the database
     */
    protected function saveParticipants(bool $exists): void
    {
        // If it's newly created task, just save participants
        if (!$exists && is_array($this->taskParticipants)) {
            $this->participants()->saveMany($this->taskParticipants);
            return;
        }

        // If the task is being updated, we need to determine what to do with participants

        // Participants weren't passed to the model, so we don't need to do anything
        if (!is_array($this->taskParticipants)) {
            return;
        }

        // Participants were passed as an empty array, so we need to remove them
        if (empty($this->taskParticipants)) {
            $this->participants()->delete();
            return;
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
    }

    /**
     * Save headnote into a separate table.
     *
     */
    protected function saveHeadnote(): void
    {
        if (!is_a($this->headnote, Note::class)) {
            return;
        }

        // Check whether a headnote already exists
        $headnote = $this->notes()->where('is_headnote', true)->first();

        if (!$headnote) {
            $this->notes()->save($this->headnote);
            return;
        }

        $headnote->update([
            'contents' => $this->headnote->contents,
            'dateModified' => now(),
        ]);
    }

    /**
     * Handle temporary files associated with the task.
     */
    protected function saveTemporaryFiles(): void
    {
        if (empty($this->temporaryFiles)) {
            return;
        }

        if ($this->assocType != PKPApplication::ASSOC_TYPE_SUBMISSION) {
            return;
        }

        $submission = Repo::submission()->get((int) $this->assocId);

        foreach ($this->temporaryFiles as $temporaryFile) {

            // Save to files
            $extension = pathinfo($temporaryFile->getOriginalFileName(), PATHINFO_EXTENSION);
            $submissionDir = Repo::submissionFile()->getSubmissionDir($submission->getData('contextId'), $submission->getId());
            $fileId = app()->get('file')->add(
                $temporaryFile->getFilePath(),
                $submissionDir . '/' . uniqid() . '.' . $extension
            );
            if (!$fileId) {
                throw new Exception('Failed to save file from temporary file ID ' . $temporaryFile->getId());
            }

            // Save to submission files
            $submissionFile = Repo::submissionFile()->newDataObject([
                'fileId' => $fileId,
                'name' => [
                    Locale::getLocale() => $temporaryFile->getData('originalFileName') ?? $temporaryFile->getData('fileName'),
                ],
                'fileStage' => SubmissionFile::SUBMISSION_FILE_QUERY,
                'submissionId' => $submission->getId(),
                'uploaderUserId' => $temporaryFile->getUserId(),
                'assocType' => Application::ASSOC_TYPE_QUERY,
                'assocId' => $this->id,
            ]);

            $submissionFileId = Repo::submissionFile()->add($submissionFile);
            if (!$submissionFileId) {
                throw new Exception('Failed to save submission file from temporary file ID ' . $temporaryFile->getId());
            }

            $removed = unlink($temporaryFile->getFilePath());

            // Delete temporary file
            if ($removed) {
                DB::table('temporary_files')
                    ->where('file_id', '=', $temporaryFile->getId())
                    ->delete();
                continue;
            }

            trigger_error('Failed to remove temporary file with ID ' . $temporaryFile->getId() . ' from a server', E_USER_WARNING);
        }
    }

    /**
     * Attach existing submission files to the task.
     *
     * @param array<SubmissionFile> $existingFiles
     *
     * @return array<int> IDs of attached submission files
     */
    protected function attachSubmissionFiles(array $existingFiles): array
    {
        if (empty($this->submissionFiles)) {
            return [];
        }

        $attachedFileIds = [];

        foreach ($this->submissionFiles as $submissionFile) { /** @var SubmissionFile $submissionFile */
            // If the submission file is already attached, skip it
            if (Arr::first($existingFiles, fn (SubmissionFile $file, int $key) => $file->getId() == $submissionFile->getId())) {
                $attachedFileIds[] = $submissionFile->getId();
                continue;
            }

            $newSubmissionFile = Repo::submissionFile()->newDataObject(array_merge($submissionFile->getAllData(), [
                'assocType' => PKPApplication::ASSOC_TYPE_QUERY,
                'assocId' => $this->id,
                'sourceSubmissionFileId' => $submissionFile->getId(),
            ]));

            Repo::submissionFile()->add($newSubmissionFile);
        }

        return $attachedFileIds;
    }
}
