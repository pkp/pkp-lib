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

use APP\core\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
     * Fix all REVISION_UPLOAD log entries using a bulk read → process → batch write approach.
     *
     * Previously we have each revision upload log to capture the PREVIOUS file's
     * data instead of the newly uploaded file's data which creates an off-by-one
     * pattern in the revision chain:
     *
     * Revision chain: [file_86, file_87, file_88]
     * Wrong log data:  log1.fileId=86 (should be 87), log2.fileId=87 (should be 88)
     */
    private function fixRevisionUploadLogData(): void
    {
        // The off-by-one bug in Repository::edit() was introduced in version 3.4.0.
        // Data created before that version is correct and must not be modified.
        $cutoffDate = $this->getBugIntroductionDate();

        if ($cutoffDate === null) {
            // No version >= 3.4.0 found in history. This happens on direct
            // pre-3.4.0 → 3.6.0 upgrades where the buggy code never ran.
            // So we can safely assume as this is an upgrade from 3.3.0-x or below
            // no need to update any data in that case and nothing to fix.
            return;
        }

        // Phase 1: Bulk read all needed data (4 queries total)
        $allLogs = $this->fetchAllRevisionUploadLogs($cutoffDate);

        if ($allLogs->isEmpty()) {
            return;
        }

        $submissionFileIds = $allLogs->pluck('submission_file_id')->unique()->values();
        $logIds = $allLogs->pluck('log_id')->unique()->values();

        $allRevisions = $this->fetchAllRevisionChains($submissionFileIds);
        $allFilenames = $this->fetchAllLogFilenames($logIds);
        $allCurrentNames = $this->fetchAllCurrentNames($submissionFileIds);

        // Phase 2: Compute all corrections in PHP (0 DB calls)
        [$fileIdUpdates, $filenameLogIdsToDelete, $filenameInserts] = $this->computeCorrections(
            $allLogs,
            $allRevisions,
            $allFilenames,
            $allCurrentNames
        );

        // Phase 3: Batch write corrections (minimal queries)
        $this->batchUpdateFileIds($fileIdUpdates);
        $this->batchReplaceFilenames($filenameLogIdsToDelete, $filenameInserts);
    }

    /**
     * Determine the date when the buggy code (>= 3.4.0) was first installed.
     *
     * The off-by-one bug in PKP\submissionFile\Repository::edit() was introduced
     * in version 3.4.0. Data created before this version was installed is correct
     * and must not be modified by this migration.
     *
     * @return ?string The date_installed of the earliest version >= 3.4.0, or null if
     *                 no such version exists (meaning the buggy code never ran).
     */
    private function getBugIntroductionDate(): ?string
    {
        $product = Application::get()->getName();

        return DB::table('versions')
            ->where('product_type', 'core')
            ->where('product', $product)
            ->whereRaw('major * 1000 + minor * 100 + revision * 10 + build >= ?', [3400])
            ->orderByRaw('major * 1000 + minor * 100 + revision * 10 + build ASC')
            ->limit(1)
            ->value('date_installed');
    }

    /**
     * Fetch all REVISION_UPLOAD log entries with their fileId settings,
     * limited to entries created on or after the cutoff date.
     *
     * @param string $cutoffDate Only include logs with date_logged >= this value.
     *                           This filters out correct pre-3.4.0 data.
     */
    private function fetchAllRevisionUploadLogs(string $cutoffDate): Collection
    {
        return DB::table('event_log AS el')
            ->join('event_log_settings AS els', function ($join) {
                $join->on('els.log_id', '=', 'el.log_id')
                    ->where('els.setting_name', '=', 'fileId');
            })
            ->where('el.event_type', self::EVENT_TYPE_REVISION_UPLOAD)
            ->where('el.assoc_type', self::ASSOC_TYPE_SUBMISSION_FILE)
            ->where('el.date_logged', '>=', $cutoffDate)
            ->select([
                'el.log_id',
                'el.assoc_id AS submission_file_id',
                'el.date_logged',
                'els.setting_value AS logged_file_id',
            ])
            ->orderBy('el.assoc_id')
            ->orderBy('el.date_logged')
            ->get();
    }

    /**
     * Fetch all revision chains for the given submission file IDs.
     *
     * @return Collection Keyed by submission_file_id
     */
    private function fetchAllRevisionChains(Collection $submissionFileIds): Collection
    {
        return DB::table('submission_file_revisions')
            ->whereIn('submission_file_id', $submissionFileIds)
            ->orderBy('submission_file_id')
            ->orderBy('revision_id')
            ->get(['submission_file_id', 'file_id'])
            ->groupBy('submission_file_id');
    }

    /**
     * Fetch all filename settings for the given log IDs.
     *
     * @return Collection Keyed by log_id
     */
    private function fetchAllLogFilenames(Collection $logIds): Collection
    {
        return DB::table('event_log_settings')
            ->whereIn('log_id', $logIds)
            ->where('setting_name', 'filename')
            ->get(['log_id', 'locale', 'setting_value'])
            ->groupBy('log_id');
    }

    /**
     * Fetch all current submission_file_settings names for the given submission file IDs.
     *
     * @return Collection Keyed by submission_file_id
     */
    private function fetchAllCurrentNames(Collection $submissionFileIds): Collection
    {
        return DB::table('submission_file_settings')
            ->whereIn('submission_file_id', $submissionFileIds)
            ->where('setting_name', 'name')
            ->get(['submission_file_id', 'locale', 'setting_value'])
            ->groupBy('submission_file_id');
    }

    /**
     * Compute all corrections needed by processing the pre-fetched data
     *
     * @return array{0: array<int,int>, 1: array<int>, 2: array<array>}
     *   [0] fileIdUpdates: [log_id => correct_file_id]
     *   [1] filenameLogIdsToDelete: log_ids whose filename entries should be replaced
     *   [2] filenameInserts: rows to insert as correct filename entries
     */
    private function computeCorrections(
        Collection $allLogs,
        Collection $allRevisions,
        Collection $allFilenames,
        Collection $allCurrentNames
    ): array {
        $fileIdUpdates = [];
        $filenameLogIdsToDelete = [];
        $filenameInserts = [];

        $logsBySubmissionFile = $allLogs->groupBy('submission_file_id');

        foreach ($logsBySubmissionFile as $submissionFileId => $logs) {
            $revisions = $allRevisions->get($submissionFileId);

            // if we have no revision or there is been only one revision,
            // means there is just one initial file upload for which no correction needed
            if (!$revisions || $revisions->count() < 2) {
                continue;
            }

            $revisionChain = $revisions->pluck('file_id')->all();

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
                    $fileIdUpdates[$log->log_id] = $nextFileIdMap[$loggedFileId];
                }
            }

            if (empty($wrongLogs)) {
                continue;
            }

            // Fix filenames only when ALL log entries in this group are wrong (consistent pattern)
            if (count($wrongLogs) !== $logs->count()) {
                continue;
            }

            // Compute filename corrections using the shifted-chain approach:
            // log[i]'s correct filename = log[i+1]'s current (wrong) filename
            // Last log's correct filename = current submission_file_settings name
            $logsArray = $logs->values()->all();
            $logCount = count($logsArray);

            $filenamesByLogId = [];
            foreach ($logsArray as $log) {
                $logFilenames = $allFilenames->get($log->log_id);
                $filenamesByLogId[$log->log_id] = $logFilenames
                    ? $logFilenames->pluck('setting_value', 'locale')->all()
                    : [];
            }

            $currentNames = $allCurrentNames->get($submissionFileId);
            $currentNamesMap = $currentNames
                ? $currentNames->pluck('setting_value', 'locale')->all()
                : [];

            for ($i = 0; $i < $logCount; $i++) {
                $logId = $logsArray[$i]->log_id;

                if ($i < $logCount - 1) {
                    $correctNames = $filenamesByLogId[$logsArray[$i + 1]->log_id];
                } else {
                    $correctNames = $currentNamesMap;
                }

                if (empty($correctNames)) {
                    continue;
                }

                $filenameLogIdsToDelete[] = $logId;

                foreach ($correctNames as $locale => $name) {
                    $filenameInserts[] = [
                        'log_id' => $logId,
                        'setting_name' => 'filename',
                        'locale' => $locale,
                        'setting_value' => $name,
                    ];
                }
            }
        }

        return [$fileIdUpdates, $filenameLogIdsToDelete, $filenameInserts];
    }

    /**
     * Batch update fileId settings using chunked CASE/WHEN SQL.
     *
     * @param array<int,int> $fileIdUpdates [log_id => correct_file_id]
     */
    private function batchUpdateFileIds(array $fileIdUpdates): void
    {
        if (empty($fileIdUpdates)) {
            return;
        }

        foreach (array_chunk($fileIdUpdates, 1000, true) as $chunk) {
            $cases = [];
            $logIds = [];

            foreach ($chunk as $logId => $correctFileId) {
                $cases[] = sprintf('WHEN %d THEN \'%d\'', (int) $logId, (int) $correctFileId);
                $logIds[] = (int) $logId;
            }

            $caseSql = implode(' ', $cases);
            $logIdList = implode(',', $logIds);

            DB::statement(
                "UPDATE event_log_settings SET setting_value = CASE log_id {$caseSql} END WHERE log_id IN ({$logIdList}) AND setting_name = 'fileId'"
            );
        }
    }

    /**
     * Replace filename settings by deleting old entries and inserting correct ones.
     *
     * @param array<int> $logIdsToDelete Log IDs whose filename entries should be replaced
     * @param array<array> $inserts Rows to insert as correct filename entries
     */
    private function batchReplaceFilenames(array $logIdsToDelete, array $inserts): void
    {
        if (empty($logIdsToDelete)) {
            return;
        }

        // Delete old filename entries for affected logs
        foreach (array_chunk(array_unique($logIdsToDelete), 1000) as $chunk) {
            DB::table('event_log_settings')
                ->whereIn('log_id', $chunk)
                ->where('setting_name', 'filename')
                ->delete();
        }

        // Insert correct filename entries
        foreach (array_chunk($inserts, 500) as $chunk) {
            DB::table('event_log_settings')->insert($chunk);
        }
    }
}
