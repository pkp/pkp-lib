<?php

/**
 * @file classes/editorialTask/Template.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Template
 *
 * @ingroup editorialTask
 *
 * @brief Class representing templates for the editorial tasks and discussions
 */

namespace PKP\editorialTask;

use APP\submission\Submission;
use DateInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PKP\core\PKPApplication;
use PKP\core\traits\ModelWithSettings;
use PKP\editorialTask\EditorialTask as Task;
use PKP\stageAssignment\StageAssignment;
use PKP\userGroup\UserGroup;

class Template extends Model
{
    use ModelWithSettings;

    protected $table = 'edit_task_templates';
    protected $primaryKey = 'edit_task_template_id';

    public $timestamps = true;

    // columns on edit_task_templates
    protected $fillable = [
        'stageId',
        'title',
        'contextId',
        'include',
        'type',
        'dueInterval',
        'description',
    ];

    protected $casts = [
        'stage_id' => 'int',
        'context_id' => 'int',
        'include' => 'bool',
        'title' => 'string',
    ];

    /**
     * @inheritDoc
     */
    public function getSettingsTable(): string
    {
        return 'edit_task_template_settings';
    }

    /**
     * @inheritDoc
     */
    public static function getSchemaName(): ?string
    {
        return null;
    }

    /**
     * Add Model-level defined multilingual properties
     */
    public function getMultilingualProps(): array
    {
        return array_merge(
            $this->multilingualProps,
            []
        );
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
     * Link template to user groups via pivot table.
     */
    public function userGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            UserGroup::class,
            'edit_task_template_user_groups',
            'edit_task_template_id',
            'user_group_id'
        );
    }

    /**
     * Scope: filter by context_id
     */
    public function scopeByContextId($query, int $contextId)
    {
        return $query->where('context_id', $contextId);
    }

    /**
     * Query scope order by the model's primary key in descending order
     */
    public function scopeOrderByPkDesc(Builder $query): Builder
    {
        return $query->orderByDesc($query->getModel()->getKeyName());
    }

    /**
     * Scope: filter by stage_id
     */
    public function scopeFilterByStageId(Builder $query, int $stageId): Builder
    {
        return $query->where('stage_id', $stageId);
    }

    /**
     * Scope: filter by include flag
     */
    public function scopeFilterByInclude(Builder $query, bool $include): Builder
    {
        return $query->where('include', $include);
    }

    /**
     * Scope: filter by email_template_key
     */
    public function scopeFilterByEmailTemplateKey(Builder $query, string $key): Builder
    {
        return $query->where('email_template_key', $key);
    }

    /**
     * Creates a new task from a template
     */
    public function promote(Submission $submission): Task
    {
        $userGroupIds = $this->userGroups()->pluck('user_groups.user_group_id')->toArray();
        $stageAssignments = StageAssignment::where('submission_id', $submission->getId())->whereHas('userGroupStages', fn (Builder $query) => $query->where('stage_id', $this->stage_id))
            ->whereHas('userGroup', fn (Builder $query) => $query->whereIn('user_group_id', $userGroupIds))
            ->get();

        $participantIds = $stageAssignments->pluck('user_id')->unique()->map(function (int $userId) {
            return ['userId' => $userId, 'isResponsible' => false];
        });

        return new Task([
            'type' => $this->type,
            'title' => $this->title,
            EditorialTask::ATTRIBUTE_HEADNOTE => $this->description,
            EditorialTask::ATTRIBUTE_PARTICIPANTS => $participantIds,
            'stageId' => $this->stageId,
            'dateDue' => $this->dueInterval ? now()->add(new DateInterval($this->dueInterval)) : null,
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
        ]);
    }

}
