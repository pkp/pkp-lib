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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PKP\core\traits\ModelWithSettings;
use PKP\emailTemplate\EmailTemplate;

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
        'email_template_id',
    ];

    protected $casts = [
        'stage_id' => 'int',
        'context_id' => 'int',
        'include' => 'bool',
        'email_template_id' => 'int',
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
     * Discussion template is associated with an email template
     * After the task is created, the email template body text is used to start a discussion
     */
    public function emailTemplate()
    {
        return $this->hasOne(EmailTemplate::class, 'email_id', 'email_template_id');
    }

    /**
     * Link template to user groups via pivot table.
     */
    public function userGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            \PKP\userGroup\UserGroup::class,
            'edit_task_template_user_groups',
            'edit_task_template_id',
            'user_group_id'
        );
    }
}
