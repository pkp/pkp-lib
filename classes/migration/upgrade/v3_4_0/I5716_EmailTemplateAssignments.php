<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I5716_EmailTemplateAssignments.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I5716_EmailTemplateAssignments
 *
 * @brief Refactors relationship between Mailables and Email Templates
 */

namespace PKP\migration\upgrade\v3_4_0;

use APP\facades\Repo;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\db\XMLDAO;
use PKP\facades\Locale;
use PKP\migration\Migration;
use stdClass;

abstract class I5716_EmailTemplateAssignments extends Migration
{
    /** @var array $newEmailIds New emails created during the migration that should be removed on downgrade */
    public array $newEmailIds = [];

    abstract protected function getContextTable(): string;
    abstract protected function getContextSettingsTable(): string;
    abstract protected function getContextIdColumn(): string;

    public function up(): void
    {
        $contextIds = DB::table($this->getContextTable())
            ->pluck($this->getContextIdColumn());

        $this->moveDisabledEmailTemplateSettings($contextIds);
        $this->migrateNotificationCenterTemplates();

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropColumn('enabled');
            $table->string('alternate_to', 255)->nullable();
            $table->index(['alternate_to'], 'email_templates_alternate_to');
        });

        Schema::table('email_templates_default_data', function (Blueprint $table) {
            $table->string('name', 255)->nullable();
        });

        $this->addDefaultTemplateNames();

        Schema::table('email_templates_default_data', function (Blueprint $table) {
            $table->string('name', 255)->change();
        });

        // The order below is important
        $this->assignIncludedAlternateTemplates($contextIds);
        $this->assignRemainingCustomTemplates($contextIds);
        $this->createAlternateTemplateNames($contextIds);
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->smallInteger('enabled')->default(1);
            $table->dropIndex('email_templates_alternate_to');
            $table->dropColumn('alternate_to');
        });

        DB::table('email_templates_settings')
            ->where('setting_name', 'name')
            ->delete();

        $this->downgradeEditorAssignTemplate();
        $this->downgradeAssignRemainingCustomTemplates();
    }

    /**
     * Add context settings for disabled email templates
     *
     * This sets the `submissionAcknowledgement` and `editorialStatsEmail`
     * context settings based on whether the related email templates have
     * been disabled.
     *
     * From 3.4, emails will no longer be disabled at the email template
     * level.
     */
    public function moveDisabledEmailTemplateSettings(Collection $contextIds): void
    {
        $contextIds->each(function (int $contextId) {
            if (
                DB::table('email_templates')
                    ->where('context_id', $contextId)
                    ->where('enabled', 0)
                    ->where('email_key', 'SUBMISSION_ACK')
                    ->exists()
            ) {
                $submissionAckSetting = '';
            } elseif (
                DB::table('email_templates')
                    ->where('context_id', $contextId)
                    ->where('enabled', 0)
                    ->where('email_key', 'SUBMISSION_ACK_NOT_USER')
                    ->exists()
            ) {
                $submissionAckSetting = 'submittingAuthor';
            } else {
                $submissionAckSetting = 'allAuthors';
            }

            $statsReportSetting = !DB::table('email_templates')
                ->where('context_id', $contextId)
                ->where('enabled', 0)
                ->where('email_key', 'STATISTICS_REPORT_NOTIFICATION')
                ->exists();

            DB::table($this->getContextSettingsTable())->insert([
                [
                    $this->getContextIdColumn() => $contextId,
                    'setting_name' => 'submissionAcknowledgement',
                    'setting_value' => $submissionAckSetting,
                ],
                [
                    $this->getContextIdColumn() => $contextId,
                    'setting_name' => 'editorialStatsEmail',
                    'setting_value' => $statsReportSetting,
                ],
            ]);
        });
    }

    /**
     * Migrates all custom NOTIFICATION_CENTER_DEFAULT templates
     *
     * This was the default template for the "Notify" feature
     * from the participants list in the workflow.
     *
     * We create a copy of the default template for each discussion
     * stage mailable. Then, if journals customized this template,
     * we update the keys for that custom template and make copies
     * of it for each stage mailable.
     */
    protected function migrateNotificationCenterTemplates(): void
    {
        $alternateTos = $this->getDiscussionTemplates();

        $newDefaultKeys = clone ($alternateTos);
        $newDefaultKey = $newDefaultKeys->shift();

        DB::table('email_templates_default_data')
            ->where('email_key', 'NOTIFICATION_CENTER_DEFAULT')
            ->update(['email_key' => $newDefaultKey]);

        $newDefaultKeys->each(function (string $key) use ($newDefaultKey) {
            DB::table('email_templates_default_data')
                ->where('email_key', $newDefaultKey)
                ->get()
                ->each(function (stdClass $row) use ($key) {
                    DB::table('email_templates_default_data')
                        ->insert([
                            'email_key' => $key,
                            'locale' => $row->locale,
                            'subject' => $row->subject,
                            'body' => $row->body,
                        ]);
                });
        });

        DB::table('email_templates')
            ->where('email_key', 'NOTIFICATION_CENTER_DEFAULT')
            ->get()
            ->each(function (stdClass $row) use ($alternateTos) {
                $keys = clone $alternateTos;

                DB::table('email_templates')
                    ->where('email_id', $row->email_id)
                    ->update(['email_key' => $keys->shift()]);

                $settingsRows = DB::table('email_templates_settings')
                    ->where('email_id', $row->email_id)
                    ->get();

                $keys->each(function (string $key) use ($row, $settingsRows) {
                    DB::table('email_templates')
                        ->insert([
                            'email_key' => $key,
                            'context_id' => $row->context_id,
                        ]);
                    $newEmailId = DB::getPdo()->lastInsertId();
                    if ($settingsRows->count()) {
                        DB::table('email_templates_settings')->insert(
                            $settingsRows->map(fn (stdClass $settingsRow) => [
                                'email_id' => $newEmailId,
                                'locale' => $settingsRow->locale,
                                'setting_name' => $settingsRow->setting_name,
                                'setting_value' => $settingsRow->setting_value,
                            ])->toArray()
                        );
                    }
                });
            });
    }

    /**
     * Adds name values to the default email templates
     *
     * Localized names are defined in the emailTemplates.xml file.
     *
     * After setting the names, any default templates that don't have
     * a name will be given a name from the email key.
     */
    protected function addDefaultTemplateNames(): void
    {
        $xmlDao = new XMLDAO();
        $data = $xmlDao->parseStruct('registry/emailTemplates.xml', ['email']);
        if (!isset($data['email'])) {
            throw new Exception('Failed to load or parse registry/emailTemplates.xml');
        }

        $locales = json_decode(
            DB::table('site')
                ->pluck('installed_locales')
                ->first()
        );

        $initialMissingLocaleKeyHandler = Locale::getMissingKeyHandler();
        Locale::setMissingKeyHandler(fn (string $key): string => '');

        foreach ($locales as $locale) {
            Locale::setLocale($locale);

            foreach ($data['email'] as $entry) {
                $key = $entry['attributes']['key'];
                $name = $entry['attributes']['name'];
                $name = __($name, [], $locale);
                DB::table('email_templates_default_data')
                    ->where('email_key', $key)
                    ->where('locale', $locale)
                    ->update(['name' => $name ? $name : $key]);
            }
        }

        Locale::setMissingKeyHandler($initialMissingLocaleKeyHandler);

        DB::table('email_templates_default_data')
            ->whereNull('name')
            ->get()
            ->each(function (stdClass $row) {
                DB::table('email_templates_default_data')
                    ->where('email_key', $row->email_key)
                    ->where('locale', $row->locale)
                    ->update(['name' => $row->email_key]);
            });
    }

    /**
     * Creates entries in email_templates for all of the alternate
     * templates that are included by default in the application
     *
     * These are the old email templates that would be available
     * in the "notify participant" feature.
     *
     * This migration will make them "alternate to" the appropriate
     * discussion mailables.
     *
     * It sets the alternate_to column and creates copies for
     * the discussion mailable in each stage.
     */
    protected function assignIncludedAlternateTemplates(Collection $contextIds): void
    {
        $contextIds->each(function (int $contextId) {
            foreach ($this->mapIncludedAlternateTemplates() as $key => $alternateTo) {
                DB::table('email_templates')->updateOrInsert(
                    [
                        'email_key' => $key,
                        'context_id' => $contextId,
                    ],
                    [
                        'alternate_to' => $alternateTo,
                    ]
                );
            }
        });

        $this->modifyEditorAssignTemplate($contextIds);
    }

    /**
     * Install the new EDITOR_ASSIGN_<stage> templates
     *
     * Install the default templates. If a custom template has been
     * created for the EDITOR_ASSIGN template, copy this custom
     * template to the new EDITOR_ASSIGN_<stage> templates.
     */
    protected function modifyEditorAssignTemplate(Collection $contextIds): void
    {
        $newTemplates = $this->mapEditorAssignTemplates();

        $newTemplates->each(
            function (string $alternateTo, string $key) {
                Repo::emailTemplate()->dao->installEmailTemplates(
                    Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(),
                    [],
                    $key
                );
            }
        );

        $contextIds->each(function (int $contextId) use ($newTemplates) {
            $customTemplateId = DB::table('email_templates')
                ->where('context_id', $contextId)
                ->where('email_key', 'EDITOR_ASSIGN')
                ->pluck('email_id')
                ->first();

            if (!$customTemplateId) {
                return;
            }

            $rows = DB::table('email_templates_settings')
                ->where('email_id', $customTemplateId)
                ->get();

            DB::table('email_templates')
                ->where('context_id', $contextId)
                ->whereIn('email_key', $newTemplates->keys())
                ->pluck('email_id')
                ->each(function (int $emailId) use ($rows) {
                    DB::table('email_templates_settings')
                        ->insert(
                            $rows->map(
                                function (stdClass $row) use ($emailId) {
                                    return [
                                        'email_id' => $emailId,
                                        'locale' => $row->locale,
                                        'setting_name' => $row->setting_name,
                                        'setting_value' => $row->setting_value,
                                    ];
                                }
                            )->toArray()
                        );
                });
        });
    }

    /**
     * Reset the editor assign template to its original key
     */
    protected function downgradeEditorAssignTemplate(): void
    {
        $emailIds = DB::table('email_templates')
            ->where('email_key', 'EDITOR_ASSIGN_SUBMISSION')
            ->orWhere('email_key', 'EDITOR_ASSIGN_REVIEW')
            ->orWhere('email_key', 'EDITOR_ASSIGN_PRODUCTION')
            ->pluck('email_id');

        DB::table('email_templates')
            ->whereIn('email_id', $emailIds->toArray())
            ->delete();

        DB::table('email_templates_settings')
            ->whereIn('email_id', $emailIds->toArray())
            ->delete();
    }

    /**
     * Assign all remaining unassigned custom templates to the
     * discussion stage mailables.
     *
     * Assigns the existing custom template to the first discussion
     * mailable that exists. Then creates copies of the template for
     * each of the remaining discussions.
     */
    protected function assignRemainingCustomTemplates(Collection $contextIds): void
    {
        $contextIds->each(function (int $contextId) {
            DB::table('email_templates as et')
                ->leftJoin('email_templates_default_data as etdd', 'et.email_key', '=', 'etdd.email_key')
                ->where('et.context_id', $contextId)
                ->whereNull('et.alternate_to')
                ->whereNull('etdd.email_key')
                ->get(['et.email_id', 'et.email_key'])
                ->each(function (stdClass $row) use ($contextId) {
                    $alternateTos = $this->getDiscussionTemplates();

                    DB::table('email_templates')
                        ->where('email_id', $row->email_id)
                        ->update(['alternate_to' => $alternateTos->shift()]);

                    if (!$alternateTos->count()) {
                        return;
                    }

                    $settingsRows = DB::table('email_templates_settings')
                        ->where('email_id', $row->email_id)
                        ->get();

                    $alternateTos->each(function (string $alternateTo) use ($row, $settingsRows, $contextId) {
                        DB::table('email_templates')->insert([
                            'email_key' => $row->email_key . '_' . $alternateTo,
                            'context_id' => $contextId,
                            'alternate_to' => $alternateTo,
                        ]);

                        $newEmailId = DB::getPdo()->lastInsertId();

                        $settingsRows->each(function (stdClass $settingsRow) use ($newEmailId) {
                            DB::table('email_templates_settings')->insert([
                                'email_id' => $newEmailId,
                                'locale' => $settingsRow->locale,
                                'setting_name' => $settingsRow->setting_name,
                                'setting_value' => $settingsRow->setting_value,
                            ]);
                        });

                        $this->newEmailIds[] = $newEmailId;
                    });
                });
        });
    }

    /**
     * Delete the extra copies of custom templates created
     * in self::assignRemainingCustomTemplates()
     */
    protected function downgradeAssignRemainingCustomTemplates(): void
    {
        DB::table('email_templates')
            ->whereIn('email_id', $this->newEmailIds)
            ->delete();
        DB::table('email_templates_settings')
            ->whereIn('email_id', $this->newEmailIds)
            ->delete();
    }

    /**
     * Create a name for all custom templates that are an alternate
     * to another template.
     *
     * Generates the name from the email key. Example:
     *
     * MY_EXAMPLE_KEY becomes MY EXAMPLE KEY
     *
     */
    protected function createAlternateTemplateNames(Collection $contextIds): void
    {
        $primaryLocales = DB::table($this->getContextTable())
            ->get([
                $this->getContextIdColumn() . ' as context_id',
                'primary_locale',
            ]);

        $contextIds->each(function (int $contextId) use ($primaryLocales) {
            $primaryLocale = $primaryLocales
                ->first(fn ($row) => $row->context_id === $contextId)
                ->primary_locale;

            $nameRows = DB::table('email_templates')
                ->where('context_id', $contextId)
                ->whereNotNull('alternate_to')
                ->get(['email_id', 'email_key'])
                ->map(function ($row) use ($primaryLocale) {
                    return [
                        'email_id' => $row->email_id,
                        'locale' => $primaryLocale,
                        'setting_name' => 'name',
                        'setting_value' => str_replace('_', ' ', $row->email_key),
                    ];
                });

            DB::table('email_templates_settings')->insert($nameRows->toArray());
        });
    }

    /**
     * Get a map of the alternate templates to be reassigned
     *
     * @return [email_key => alternate_to]
     */
    protected function mapIncludedAlternateTemplates(): array
    {
        return [
            'COPYEDIT_REQUEST' => 'DISCUSSION_NOTIFICATION_COPYEDITING',
            'LAYOUT_REQUEST' => 'DISCUSSION_NOTIFICATION_PRODUCTION',
            'LAYOUT_COMPLETE' => 'DISCUSSION_NOTIFICATION_PRODUCTION',
        ];
    }

    /**
     * Get a map of the EDITOR_ASSIGN_<stage> templates
     *
     * @return [email_key => alternate_to]
     */
    protected function mapEditorAssignTemplates(): Collection
    {
        return collect([
            'EDITOR_ASSIGN_SUBMISSION' => 'DISCUSSION_NOTIFICATION_SUBMISSION',
            'EDITOR_ASSIGN_REVIEW' => 'DISCUSSION_NOTIFICATION_REVIEW',
            'EDITOR_ASSIGN_PRODUCTION' => 'DISCUSSION_NOTIFICATION_PRODUCTION',
        ]);
    }

    /**
     * Get all discussion mailable temlate keys in this app
     */
    abstract protected function getDiscussionTemplates(): Collection;
}
