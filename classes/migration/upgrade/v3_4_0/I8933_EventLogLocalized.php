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

use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

abstract class I8933_EventLogLocalized extends Migration
{
    public const CHUNK_SIZE = 10000;

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
            $table->dropColumn('setting_type');
        });

        // Events can be triggered without a user, e.g., in schedule tasks
        Schema::table('event_log', function (Blueprint $table) {
            $table->dropForeign('event_log_user_id_foreign');
            $table->dropIndex('event_log_user_id');
            $table->bigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'event_log_user_id');
            if (DB::connection() instanceof PostgresConnection) {
                DB::unprepared('ALTER TABLE event_log ALTER is_translated TYPE bool USING CASE WHEN COALESCE(is_translated, 0) = 0 THEN FALSE ELSE TRUE END');
            } else {
                $table->boolean('is_translated')->nullable()->change();
            }
        });

        $this->fixConflictingSubmissionLogConstants();

        // Rename ambiguous settings
        $this->renameSettings();

        // Localize existing submission file name entries
        $sitePrimaryLocale = DB::table('site')->value('primary_locale');
        $contexts = DB::table($this->getContextTable())->get([$this->getContextIdColumn(), 'primary_locale']);
        $contextIdPrimaryLocaleMap = [];
        foreach ($contexts as $context) {
            $contextIdPrimaryLocaleMap[$context->{$this->getContextIdColumn()}] = $context->primary_locale;
        }

        DB::table('event_log_settings AS es')
            ->join('event_log AS e', 'es.log_id', '=', 'e.log_id')
            ->where('es.setting_name', 'filename')
            ->whereIn('e.event_type', [
                0x50000001, // SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD,
                0x50000010, // SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_EDIT,
                0x50000008, // SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_REVISION_UPLOAD,
            ])
            ->chunkById(
                self::CHUNK_SIZE,
                function (Collection $logChunks) use ($sitePrimaryLocale, $contextIdPrimaryLocaleMap) {
                    $mapLocaleWithSettingIds = [];
                    foreach ($logChunks as $row) {
                        // Get locale based on a submission file ID log entry
                        $locale = $this->getContextPrimaryLocale($row, $sitePrimaryLocale, $contextIdPrimaryLocaleMap);
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
                },
                'es.event_log_setting_id',
                'event_log_setting_id'
            );
    }

    /**
     * FIX event types with identical values
     * 0x40000020: SUBMISSION_LOG_DECISION_EMAIL_SENT => 0x30000007, SUBMISSION_LOG_REVIEW_REMIND => 0x40000020, SUBMISSION_LOG_REVIEW_REMIND_AUTO => 0x40000021
     */
    protected function fixConflictingSubmissionLogConstants(): void
    {
        DB::statement(
            "UPDATE event_log el
            SET event_type = CASE
                WHEN (
                    SELECT COUNT(0)
                    FROM event_log_settings els
                    WHERE els.log_id = el.log_id
                    AND els.setting_name IN ('recipientCount', 'subject')
                ) = 2
                    THEN ?
                WHEN NOT EXISTS (
                    SELECT COUNT(0)
                    FROM event_log_settings els
                    WHERE els.log_id = el.log_id
                    AND els.setting_name IN ('senderId', 'senderName')
                )
                    THEN ?
                ELSE
                    el.event_type
            END
            WHERE el.event_type = ?
        ", [
            0x30000007, // SUBMISSION_LOG_DECISION_EMAIL_SENT
            0x40000021, // SUBMISSION_LOG_REVIEW_REMIND_AUTO
            0x40000020, // SUBMISSION_LOG_REVIEW_REMIND
        ]);
    }

    /**
     * Retrieve the primary locale of the context associated with a given submission file
     */
    protected function getContextPrimaryLocale(object $row, string $sitePrimaryLocale, array $contextIdPrimaryLocaleMap): ?string
    {
        // Try to determine submission/submission file ID based on the assoc type
        if ($row->assoc_type === 0x0000203) { // ASSOC_TYPE_SUBMISSION_FILE
            $submissionFileId = $row->assoc_id;
        } elseif ($row->assoc_type === 0x0100009) { // ASSOC_TYPE_SUBMISSION
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

        return $contextIdPrimaryLocaleMap[$contextId];
    }

    /**
     * Rename setting name to avoid ambiguity in the event log schema
     */
    protected function renameSettings()
    {
        // First remove 'originalFileName' setting where 'name' setting exists
        DB::table('event_log AS e')
            ->join('event_log_settings AS es', 'es.log_id', '=', 'e.log_id')
            ->where('es.setting_name', 'originalFileName')
            ->whereIn('e.event_type', [
                0x50000008, // SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_REVISION_UPLOAD,
                0x50000010, // SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_EDIT
            ])
            ->whereExists(fn (Builder $q) => $q->from('event_log_settings AS els')
                ->where('els.setting_name', 'name')
                ->whereColumn('els.log_id', '=', 'e.log_id')
            )
            ->delete();

        // Perform setting renaming
        foreach ($this->mapSettings() as $eventType => $settings) {
            foreach ($settings as $oldSettingName => $newSettingName) {
                DB::table('event_log_settings AS els')
                    ->join('event_log AS el', 'el.log_id', '=', 'els.log_id')
                    ->where('el.event_type', $eventType)
                    ->where('els.setting_name', $oldSettingName)
                    ->update(['els.setting_name' => $newSettingName]);
            }
        }
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
            0x10000009 => [ // PKPSubmissionEventLogEntry::SUBMISSION_LOG_COPYRIGHT_AGREED
                'name' => 'userFullName'
            ],
            0x40000019 => [ // PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_CONFIRMED
                'userName' => 'editorName'
            ],
            0x40000011 => [ // PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_SET_DUE_DATE
                'dueDate' => 'reviewDueDate'
            ],
            0x50000001 => [ // SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD
                'originalFileName' => 'filename'
            ],
            /**
             * 'originalFileName' and 'name' are duplicate entries in some events, the former arises from the times before
             * submission files refactoring, where it had pointed to the name of the original name of the uploaded file
             * rather than the user defined localized name.
             * Keep the 'name' where it exists, otherwise preserve 'originalFileName'
             */
            0x50000008 => [ // SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_REVISION_UPLOAD
                'name' => 'filename',
                'originalFileName' => 'filename'
            ],
            0x50000010 => [ // SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_EDIT
                'name' => 'filename',
                'originalFileName' => 'filename'
            ],
            0x10000003 => [ // PKPSubmissionEventLogEntry::SUBMISSION_LOG_ADD_PARTICIPANT
                'name' => 'userFullName'
            ],
            0x10000004 => [ // PKPSubmissionEventLogEntry::SUBMISSION_LOG_REMOVE_PARTICIPANT
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
