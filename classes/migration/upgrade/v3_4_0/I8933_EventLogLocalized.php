<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8933_EventLogLocalized.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8933_EventLogLocalized.php
 *
 * @brief Adds a column to the event_log_settings table to store localized data such as a file name and drops setting_type column.
 * In the event_log table allows null values for userId.
 * Fixes the issue with duplicate event types and renames conflicting setting names
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\log\event\SubmissionFileEventLogEntry;
use PKP\migration\Migration;

abstract class I8933_EventLogLocalized extends Migration
{
    abstract protected function getContextTable(): string;

    abstract protected function getContextIdColumn(): string;

    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('event_log_settings', function (Blueprint $table) {
            $table->string('locale', 14)->default('')->after('log_id');
            $table->dropUnique('event_log_settings_unique');
            $table->unique(['log_id', 'locale', 'setting_name'], 'event_log_settings_unique');
            $table->string('setting_type', 6)->nullable()->change();
        });

        // Events can be triggered without a user, e.g., in schedule tasks
        Schema::table('event_log', function (Blueprint $table) {
            $table->dropForeign('event_log_user_id_foreign');
            $table->dropIndex('event_log_user_id');
            $table->bigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'event_log_user_id');
            $table->boolean('is_translated')->change();
        });

        $this->fixConflictingSubmissionLogConstants();

        // Rename ambiguous settings
        $this->renameSettings();

        // Localize existing submission file name entries
        $sitePrimaryLocale = DB::table('site')->value('primary_locale');
        DB::table('event_log_settings AS es')
            ->join('event_log AS e', 'es.log_id', '=', 'e.log_id')
            ->where('setting_name', 'filename')
            ->whereIn('event_type', [
                SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD,
                SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_EDIT,
                SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_REVISION_UPLOAD,
            ])
            ->orderBy('event_log_setting_id')
            ->chunk(100, function (Collection $logChunks) use ($sitePrimaryLocale) {
                $mapLocaleWithSettingIds = [];
                foreach ($logChunks as $row) {
                    // Get locale based on a submission file ID log entry
                    $locale = $this->getContextPrimaryLocale($row, $sitePrimaryLocale);
                    if (!$locale) {
                        continue;
                    }
                    $mapLocaleWithSettingIds[$locale] ??= [];
                    $mapLocaleWithSettingIds[$locale][] = $row->event_log_setting_id;
                }

                // Update by chunks for each locale
                foreach ($mapLocaleWithSettingIds as $locale => $settingIds) {
                    DB::table('event_log_settings')
                        ->whereIn('event_log_setting_id', $settingIds)
                        ->update(['locale' => $locale]);
                }
            });
    }

    /**
     * FIX event types with identical values
     * 0x40000020: SUBMISSION_LOG_DECISION_EMAIL_SENT => 0x30000007, SUBMISSION_LOG_REVIEW_REMIND => 0x40000020, SUBMISSION_LOG_REVIEW_REMIND_AUTO => 0x40000021
     */
    protected function fixConflictingSubmissionLogConstants(): void
    {
        DB::table('event_log')->where('event_type', 0x40000020)->lazyById(1000, 'log_id')->each(function (object $row) {
            if (
                DB::table('event_log_settings')
                    ->where('log_id', $row->log_id)
                    ->whereIn('setting_name', ['recipientCount', 'subject'])
                    ->count() === 2
            ) {
                DB::table('event_log')
                    ->where('log_id', $row->log_id)
                    ->update(['event_type' => 0x30000007]); // SUBMISSION_LOG_DECISION_EMAIL_SENT
            } else if (
                !DB::table('event_log_settings')
                    ->where('log_id', $row->log_id)
                    ->whereIn('setting_name', ['senderId', 'senderName'])
                    ->exists()
            ) {
                DB::table('event_log')
                    ->where('log_id', $row->log_id)
                    ->update(['event_type' => 0x40000021]); // SUBMISSION_LOG_REVIEW_REMIND_AUTO
            }
        });
    }

    /**
     * Retrieve the primary locale of the context associated with a given submission file
     */
    protected function getContextPrimaryLocale(object $row, string $sitePrimaryLocale): ?string
    {
        // Try to determine submission/submission file ID based on the assoc type
        if ($row->assoc_type === 0x0000203) { // ASSOC_TYPE_SUBMISSION_FILE
            $submissionFileId = $row->assoc_id;
        } else if ($row->assoc_type === 0x0100009) { // ASSOC_TYPE_SUBMISSION
            $submissionId = $row->assoc_id;
        } else {
            throw new \Exception('Unsupported assoc_type in the event log: ' . $row->assoc_type);
        }

        // Get submission from the file ID
        if (!isset($submissionId)) {
            $submissionId = DB::table('submission_files')
                ->where('submission_file_id', $submissionFileId)
                ->value('submission_id');
        }

        // Assuming submission file was removed
        if (!$submissionId) {
            return $sitePrimaryLocale;
        }

        $contextId = DB::table('submissions')->where('submission_id', $submissionId)->value('context_id');

        if (!$contextId) {
            return $sitePrimaryLocale;
        }

        return DB::table($this->getContextTable())->where($this->getContextIdColumn(), $contextId)->value('primary_locale');
    }

    /**
     * Rename setting name to avoid ambiguity in the event log schema
     */
    protected function renameSettings()
    {
        $eventTypes = $this->mapSettings()->keys()->toArray();
        DB::table('event_log')
            ->whereIn('event_type', $eventTypes)
            ->orderBy('date_logged')
            ->each(function (object $row) {

                // resolve conflict between 'name' and 'originalFileName'
                if (in_array(
                    $row->event_type,
                    [SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_REVISION_UPLOAD, SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_EDIT])
                ) {
                    if (DB::table('event_log_settings')->where('log_id', $row->log_id)->where('setting_name', 'name')->exists()) {
                        DB::table('event_log_settings')
                            ->where('log_id', $row->log_id)
                            ->where('setting_name', 'originalFileName')
                            ->delete();
                    }
                }

                // just rename other settings
                $oldNewSettingNames = $this->mapSettings()->get($row->event_type);
                foreach ($oldNewSettingNames as $oldSettingName => $newSettingName) {
                    DB::table('event_log_settings')
                        ->where('log_id', $row->log_id)
                        ->where('setting_name', $oldSettingName)
                        ->update(['setting_name' => $newSettingName]);
                }
            });
    }

    /**
     * Map of new setting names for the event log
     * event type => [
     *   old setting => new setting
     * ]
     */
    protected function mapSettings(): Collection
    {
        return collect([
            PKPSubmissionEventLogEntry::SUBMISSION_LOG_COPYRIGHT_AGREED => [
                'name' => 'userFullName'
            ],
            PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_CONFIRMED => [
                'userName' => 'editorName'
            ],
            PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_SET_DUE_DATE => [
                'dueDate' => 'reviewDueDate'
            ],
            SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD => [
                'originalFileName' => 'filename'
            ],
            /**
             * 'originalFileName' and 'name' are duplicate entries in some events, the former arises from the times before
             * submission files refactoring, where it had pointed to the name of the original name of the uploaded file
             * rather than the user defined localized name.
             * Keep the 'name' where it exists, otherwise preserve 'originalFileName'
             */
            SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_REVISION_UPLOAD => [
                'name' => 'filename',
                'originalFileName' => 'filename'
            ],
            SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_EDIT => [
                'name' => 'filename',
                'originalFileName' => 'filename'
            ],
            PKPSubmissionEventLogEntry::SUBMISSION_LOG_ADD_PARTICIPANT => [
                'name' => 'userFullName'
            ],
            PKPSubmissionEventLogEntry::SUBMISSION_LOG_REMOVE_PARTICIPANT => [
                'name' => 'userFullName'
            ]
        ]);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}