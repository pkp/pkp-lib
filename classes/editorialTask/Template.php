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

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PKP\core\traits\ModelWithSettings;
use PKP\userGroup\UserGroup;

class Template extends Model
{
    use ModelWithSettings;

    protected $table = 'edit_task_templates';
    protected $primaryKey = 'edit_task_template_id';

    public $timestamps = true;

    // columns on edit_task_templates
    protected $fillable = [
        'stage_id',
        'title',
        'context_id',
        'include',
        'email_template_key',
    ];

    protected $casts = [
        'stage_id' => 'int',
        'context_id' => 'int',
        'include' => 'bool',
        'title' => 'string',
        'email_template_key' => 'string',
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
            [
                'name',
                'description',
            ]
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
     * Resolve the effective email template by key:
     * prefer context override; otherwise fallback to default (NULL context_id).
     */
    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(
            \PKP\emailTemplate\EmailTemplate::class,
            'email_template_key',  // FK on this model
            'email_key'            // owner key on email_templates
        )
        ->where(function ($q) {
            $q->where('context_id', $this->context_id)
            ->orWhereNull('context_id');
        })
        ->orderByRaw('CASE WHEN context_id = ? THEN 1 ELSE 0 END DESC', [$this->context_id]);
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
     * Query scope order by the model's primary key in descending order
     */
    public function scopeOrderByPkDesc(Builder $query): Builder
    {
        return $query->orderByDesc($query->getModel()->getKeyName());
    }

}