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
use Illuminate\Support\Facades\DB;
use PKP\core\traits\ModelWithSettings;
use PKP\userGroup\UserGroup;

class Template extends Model
{
    use ModelWithSettings;

    protected $table = 'edit_task_templates';
    protected $primaryKey = 'edit_task_template_id';

    public $timestamps = true;

    public const TYPE_DISCUSSION = 1;
    public const TYPE_TASK = 2;

    // columns on edit_task_templates
    protected $fillable = [
        'stage_id',
        'title',
        'context_id',
        'include',
        'email_template_key',
        'type',
    ];

    protected $casts = [
        'stage_id' => 'int',
        'context_id' => 'int',
        'include' => 'bool',
        'title' => 'string',
        'email_template_key' => 'string',
        'type' => 'int',
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
            'email_key' // owner key on email_templates
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
     * Scope: filter by  type
     */
    public function scopeFilterByType(Builder $q, int $type): Builder
    {
        return $q->where('type', $type);
    }

    /**
     * Scope: filter by title LIKE
     */
    public function scopeFilterByTitleLike(Builder $query, string $title): Builder
    {
        $title = trim($title);
        if ($title === '') {
            return $query;
        }
        $needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($title)) . '%';
        return $query->whereRaw('LOWER(title) LIKE ?', [$needle]);
    }

    /**
     * free-text/ words search across:
     * title column
     * name, description
     * email_template_key column
     *
     */
    public function scopeFilterBySearch(Builder $query, string $phrase): Builder
    {
        $phrase = trim($phrase);
        if ($phrase === '') {
            return $query;
        }

        $tokens = preg_split('/\s+/', $phrase) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => $t !== ''));
        if (!$tokens) {
            return $query;
        }

        $settingsTable = $this->getSettingsTable(); // 'edit_task_template_settings'
        $pk = $this->getKeyName(); // 'edit_task_template_id'
        $selfTable = $this->getTable(); // 'edit_task_templates'

        return $query->where(function (Builder $outer) use ($tokens, $settingsTable, $pk, $selfTable) {
            foreach ($tokens as $tok) {
                // escape % and _
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($tok)) . '%';

                $outer->where(function (Builder $q) use ($like, $settingsTable, $pk, $selfTable) {
                    $q->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email_template_key) LIKE ?', [$like])
                    ->orWhereExists(function ($sub) use ($like, $settingsTable, $pk, $selfTable) {
                        $sub->select(DB::raw(1))
                            ->from($settingsTable . ' as ets')
                            ->whereColumn("ets.$pk", "$selfTable.$pk")
                            ->whereIn('ets.setting_name', ['name', 'description'])
                            ->whereRaw('LOWER(ets.setting_value) LIKE ?', [$like]);
                    });
                });
            }
        });
    }

}
