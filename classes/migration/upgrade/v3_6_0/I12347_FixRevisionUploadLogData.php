<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12347_FixRevisionUploadLogData.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12347_FixRevisionUploadLogData
 *
 * @brief Fix incorrect event_log data for submission file revision uploads where
 *        fileId and filename pointed to the previous revision instead of the new one.
 *
 * @see https://github.com/pkp/pkp-lib/issues/12347
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I12347_FixRevisionUploadLogData extends Migration
{
    /**
     * PKPApplication::ASSOC_TYPE_SUBMISSION_FILE
     */
    private const ASSOC_TYPE_SUBMISSION_FILE = 0x0000203;

    /**
     * SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_REVISION_UPLOAD
     */
    private const EVENT_TYPE_REVISION_UPLOAD = 1342177288; // 0x50000008

    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->fixRevisionUploadLogData();
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    /**
     * Find all submission files with REVISION_UPLOAD log entries and fix
     * their fileId and filename settings.
     */
    private function fixRevisionUploadLogData(): void
    {
        $submissionFileIds = DB::table('event_log')
            ->where('event_type', self::EVENT_TYPE_REVISION_UPLOAD)
            ->where('assoc_type', self::ASSOC_TYPE_SUBMISSION_FILE)
            ->distinct()
            ->pluck('assoc_id');

        foreach ($submissionFileIds as $submissionFileId) {
            $this->fixLogsForSubmissionFile((int) $submissionFileId);
        }
    }

    /**
     * Fix fileId and filename for all REVISION_UPLOAD log entries
     * belonging to a single submission file.
     *
     * The bug caused each revision upload log to capture the PREVIOUS file's
     * data instead of the newly uploaded file's data. This creates an off-by-one
     * pattern in the revision chain:
     *
     * Revision chain: [file_86, file_87, file_88]
     * Wrong log data:  log1.fileId=86 (should be 87), log2.fileId=87 (should be 88)
     */
    private function fixLogsForSubmissionFile(int $submissionFileId): void
    {
        // Get the revision chain for this submission file
        $revisionChain = DB::table('submission_file_revisions')
            ->where('submission_file_id', $submissionFileId)
            ->orderBy('revision_id')
            ->pluck('file_id')
            // ->map(fn ($id) => (int) $id)
            ->all();

        // If there is only one entry, means there has been no revision upload being uploaded
        // for this submission file and does not need any correction via migration
        if (count($revisionChain) < 2) {
            return;
        }

        // Get all REVISION_UPLOAD log entries with their fileId settings
        $logs = DB::table('event_log AS el')
            ->join('event_log_settings AS els', function ($join) {
                $join->on('els.log_id', '=', 'el.log_id')
                    ->where('els.setting_name', '=', 'fileId');
            })
            ->where('el.event_type', self::EVENT_TYPE_REVISION_UPLOAD)
            ->where('el.assoc_type', self::ASSOC_TYPE_SUBMISSION_FILE)
            ->where('el.assoc_id', $submissionFileId)
            ->select([
                'el.log_id',
                'el.date_logged',
                'els.setting_value AS logged_file_id',
            ])
            ->orderBy('el.date_logged')
            ->get();

        if ($logs->isEmpty()) {
            return;
        }

        // Build a map: file_id => next_file_id in the revision chain
        $nextFileIdMap = [];
        for ($i = 0; $i < count($revisionChain) - 1; $i++) {
            $nextFileIdMap[$revisionChain[$i]] = $revisionChain[$i + 1];
        }

        // Identify which log entries have the off-by-one bug
        $wrongLogs = [];
        foreach ($logs as $log) {
            $loggedFileId = (int) $log->logged_file_id;
            if (isset($nextFileIdMap[$loggedFileId])) {
                $wrongLogs[] = $log;
            }
        }

        if (empty($wrongLogs)) {
            return;
        }

        // Fix fileId for all wrong entries
        foreach ($wrongLogs as $log) {
            $correctFileId = $nextFileIdMap[(int) $log->logged_file_id];

            DB::table('event_log_settings')
                ->where('log_id', $log->log_id)
                ->where('setting_name', 'fileId')
                ->update(['setting_value' => (string) $correctFileId]);
        }

        // Fix filenames using the shifted-chain approach.
        // Only safe when ALL log entries in this group are wrong (consistent pattern).
        $this->fixFilenamesForSubmissionFile($submissionFileId, $logs, $wrongLogs);
    }

    /**
     * Fix the filename settings for REVISION_UPLOAD log entries using the
     * shifted-chain approach.
     *
     * Since each log captured the PREVIOUS revision's filename:
     * - log[i]'s correct filename = log[i+1]'s current (wrong) filename
     * - Last log's correct filename = current submission_file_settings.name
     *
     * This only runs when ALL logs in the group are confirmed wrong.
     */
    private function fixFilenamesForSubmissionFile(int $submissionFileId, Collection $allLogs, array $wrongLogs): void
    {
        if (count($wrongLogs) !== $allLogs->count()) {
            return;
        }

        $logsArray = $allLogs->values()->all();
        $logCount = count($logsArray);

        // Collect current (wrong) filenames for each log entry, per locale
        $filenamesByLogId = [];
        foreach ($logsArray as $log) {
            $filenamesByLogId[$log->log_id] = DB::table('event_log_settings')
                ->where('log_id', $log->log_id)
                ->where('setting_name', 'filename')
                ->pluck('setting_value', 'locale')
                ->all();
        }

        // Get current submission_file_settings name for the last log entry's correction
        $currentNames = DB::table('submission_file_settings')
            ->where('submission_file_id', $submissionFileId)
            ->where('setting_name', 'name')
            ->pluck('setting_value', 'locale')
            ->all();

        for ($i = 0; $i < $logCount; $i++) {
            $logId = $logsArray[$i]->log_id;

            if ($i < $logCount - 1) {
                // Correct filename = next log entry's current (wrong) filename
                $correctNames = $filenamesByLogId[$logsArray[$i + 1]->log_id];
            } else {
                // Last log: correct filename = current submission_file_settings name
                $correctNames = $currentNames;
            }

            if (empty($correctNames)) {
                continue;
            }

            // Update each locale's filename
            foreach ($correctNames as $locale => $name) {
                DB::table('event_log_settings')
                    ->updateOrInsert(
                        [
                            'log_id' => $logId,
                            'setting_name' => 'filename',
                            'locale' => $locale,
                        ],
                        ['setting_value' => $name]
                    );
            }

            // Remove locale entries that exist in the old data but not in the correct data
            $oldLocales = array_keys($filenamesByLogId[$logId] ?? []);
            $removedLocales = array_diff($oldLocales, array_keys($correctNames));

            if (!empty($removedLocales)) {
                DB::table('event_log_settings')
                    ->where('log_id', $logId)
                    ->where('setting_name', 'filename')
                    ->whereIn('locale', $removedLocales)
                    ->delete();
            }
        }
    }
}
