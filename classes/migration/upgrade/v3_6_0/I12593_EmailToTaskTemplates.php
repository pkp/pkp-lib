<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12593_EmailToTaskTemplates.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12593_EmailToTaskTemplates
 *
 * @brief Move DISCUSSION_NOTIFICATION_* email templates from the email_templates
 *        and email_templates_default_data tables into the edit_task_templates table.
 */

namespace PKP\migration\upgrade\v3_6_0;

use APP\core\Application;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I12593_EmailToTaskTemplates extends Migration
{
    // Data related to the default discussion-related templates
    protected Collection $defaultData;

    // Data related to the custom discussion-related templates, including those which override the default ones on the context level
    protected Collection $customData;

    protected Collection $customDataSettings;

    protected array $insertedTaskTemplateIds = [];

    // Template title and description
    protected Collection $taskTemplateData;

    /**
     * Maps each migrated email key to its corresponding workflow stage ID
     */
    protected function emailKeyToStageMap(): array
    {
        return [
            'DISCUSSION_NOTIFICATION_SUBMISSION' => WORKFLOW_STAGE_ID_SUBMISSION,
            'DISCUSSION_NOTIFICATION_REVIEW' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'DISCUSSION_NOTIFICATION_COPYEDITING' => WORKFLOW_STAGE_ID_EDITING,
            'DISCUSSION_NOTIFICATION_PRODUCTION' => WORKFLOW_STAGE_ID_PRODUCTION,
        ];
    }

    protected function getEmailKeys(): array
    {
        return array_keys($this->emailKeyToStageMap());
    }

    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->localizeTaskTemplateData();

        // Migration of the custom templates
        $customData = DB::table('email_templates')
            ->where(function (Builder $query) {
                $query->whereIn('email_key', $this->getEmailKeys())
                    ->orWhereIn('alternate_to', $this->getEmailKeys());
            })
            ->get();

        // Populate stage IDs
        $this->customData = $customData->map(function (\stdClass $item) {
            $item->stage_id = array_key_exists($item->email_key, $this->emailKeyToStageMap()) ?
                $this->emailKeyToStageMap()[$item->email_key] :
                $this->emailKeyToStageMap()[$item->alternate_to];

            return $item;
        });

        $customIds = $this->customData->pluck('email_id')->toArray();
        $this->customDataSettings = DB::table('email_templates_settings')->whereIn('email_id', $customIds)->get();

        // Custom templates can override default ones on the context level. Catch them by email key and group by context ID
        $alreadyMigrated = [];
        // Additional default template to migrate ['email_key' => 'stage_id']
        $migrateDefault = [];

        foreach ($this->customData as $customTemplate) {
            $localizedSettings = DB::table('email_templates_settings')
                ->where('email_id', $customTemplate->email_id)
                ->get()
                ->groupBy('locale')
                ->map(fn (Collection $rows) => $rows->pluck('setting_value', 'setting_name')->all())
                ->all();

            // The template wasn't overridden and should be installed as a default
            if (empty($localizedSettings)) {
                $migrateDefault[$customTemplate->email_key] = $customTemplate->stage_id;
                continue;
            }

            if (in_array($customTemplate->email_key, $this->getEmailKeys())) {
                $alreadyMigrated[$customTemplate->context_id][] = $customTemplate->email_key;
            }

            $templateId = DB::table('edit_task_templates')->insertGetId([
                'stage_id' => $customTemplate->stage_id,
                'context_id' => $customTemplate->context_id,
                'include' => false,
                'due_interval' => null,
                'type' => EditorialTaskType::DISCUSSION->value,
                'restrict_to_user_groups' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'edit_task_template_id');

            foreach ($localizedSettings as $locale => $setting) {
                DB::table('edit_task_template_settings')->insert([
                    [
                        'edit_task_template_id' => $templateId,
                        'locale' => $locale,
                        'setting_name' => 'title',
                        'setting_value' => $setting['name'],
                    ],
                    [
                        'edit_task_template_id' => $templateId,
                        'locale' => $locale,
                        'setting_name' => 'description',
                        'setting_value' => $setting['body'],
                    ]
                ]);
            }

            $this->insertedTaskTemplateIds[] = $templateId;
        }

        // Migration of the default templates
        $defaultData = DB::table('email_templates_default_data')
            ->whereIn('email_key', array_merge($this->getEmailKeys(), array_keys($migrateDefault)))
            ->get();

        $this->defaultData = $defaultData->map(function (\stdClass $item) use ($migrateDefault) {
            $item->stage_id = Arr::get($this->emailKeyToStageMap(), $item->email_key) ?? $migrateDefault[$item->email_key];
            return $item;
        });

        $contextDao = Application::getContextDAO();
        $contextIds = DB::table($contextDao->tableName)->pluck($contextDao->primaryKeyColumn)->all();

        foreach ($this->defaultData->groupBy('email_key') as $key => $group) {
            foreach ($contextIds as $contextId) {
                // Skip default templates, which were overridden
                if (isset($alreadyMigrated[$contextId]) && in_array($key, $alreadyMigrated[$contextId])) {
                    continue;
                }

                $insertedTaskTemplateId = DB::table('edit_task_templates')->insertGetId([
                    'stage_id' => $group[0]->stage_id,
                    'context_id' => $contextId,
                    'include' => false,
                    'due_interval' => null,
                    'type' => EditorialTaskType::DISCUSSION->value,
                    'restrict_to_user_groups' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'edit_task_template_id');

                $this->insertedTaskTemplateIds[] = $insertedTaskTemplateId;

                foreach ($group as $item) {
                    DB::table('edit_task_template_settings')->insert([
                        [
                            'edit_task_template_id' => $insertedTaskTemplateId,
                            'locale' => $item->locale,
                            'setting_name' => 'title',
                            'setting_value' => $item->name,
                        ],
                        [
                            'edit_task_template_id' => $insertedTaskTemplateId,
                            'locale' => $item->locale,
                            'setting_name' => 'description',
                            'setting_value' => $item->body,
                        ]
                    ]);
                }
            }
        }

        // Delete custom templates, related settings and access
        DB::table('email_templates')->whereIn('email_id', $customIds)->delete();
        DB::table('email_templates_settings')->whereIn('email_id', $customIds)->delete();
        DB::table('email_templates_default_data')->whereIn('email_key', $this->getEmailKeys())->delete();
        DB::table('email_template_user_group_access')->whereIn('email_key', $this->getEmailKeys())->delete();
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('edit_task_templates', function (Blueprint $table) {
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
        });

        // reverse the migration of the default data using one insert operation
        if ($this->defaultData->isNotEmpty()) {
            $toInsert = [];
            foreach ($this->defaultData as $defaultTemplate) {
                $toInsert[] = [
                    'email_key' => $defaultTemplate->email_key,
                    'locale' => $defaultTemplate->locale,
                    'name' => $defaultTemplate->name,
                    'subject' => $defaultTemplate->subject,
                    'body' => $defaultTemplate->body,
                ];
            }

            DB::table('email_templates_default_data')->insert($toInsert);
        }

        // reverse the migration of the custom templates
        foreach ($this->customData as $customTemplate) {
            $customTemplateId = DB::table('email_templates')->insertGetId([
                'email_key' => $customTemplate->email_key,
                'context_id' => $customTemplate->context_id,
                'alternate_to' => $customTemplate->alternate_to,
            ], 'email_id');

            // Find and insert setting corresponding to the custom template
            $this->customDataSettings->where('email_id', $customTemplate->email_id)
                ->each(fn ($setting) => DB::table('email_templates_settings')->insert([
                    'email_id' => $customTemplateId,
                    'locale' => $setting->locale,
                    'setting_name' => $setting->setting_name,
                    'setting_value' => $setting->setting_value,
                ]));
        }

        DB::table('edit_task_templates')->whereIn('edit_task_template_id', $this->insertedTaskTemplateIds)->delete();
        DB::table('edit_task_template_settings')->whereIn('edit_task_template_id', $this->insertedTaskTemplateIds)->delete();
        DB::table('edit_task_template_user_groups')->whereIn('edit_task_template_id', $this->insertedTaskTemplateIds)->delete();
        Schema::table('edit_task_templates', function (Blueprint $table) {
            $table->string('title', 255)->nullable(false)->change();
        });
    }

    /**
     * * @inheritDoc
     *
     * * @throws DowngradeNotSupportedException
     */
    protected function localizeTaskTemplateData(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
