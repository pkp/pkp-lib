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
     * SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_UPLOAD
     */
    private const EVENT_TYPE_FILE_UPLOAD = 0x50000001;

    /**
     * SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_REVISION_UPLOAD
     */
    private const EVENT_TYPE_REVISION_UPLOAD = 0x50000008;

    /**
     * SubmissionFileEventLogEntry::SUBMISSION_LOG_FILE_EDIT
     */
    private const EVENT_TYPE_FILE_EDIT = 0x50000010;

    /**
     * Number of submission files handled per batch.
     */
    private const CHUNK_SIZE = 1000;

    /**
     * Maximum number of rows per INSERT statement.
     */
    private const INSERT_CHUNK_SIZE = 500;

    private int $correctedCount = 0;

    private int $skippedCount = 0;

    /**
     * Run the migration.
     */
    public function up(): void
    {
        // The off-by-one bug was introduced with the submission file Repository refactor
        // during the 3.4.0 development cycle (pkp/pkp-lib#7125). Before that, the log data
        // was derived from an object that had already been updated in place, so it was
        // correct and must not be modified.
        $cutoffDate = $this->getBugIntroductionDate();

        if ($cutoffDate === null) {
            // No version >= 3.4.0 found in the upgrade history. This happens on direct
            // pre-3.4.0 → 3.6.0 upgrades where the buggy code never ran, so there is
            // nothing to fix.
            return;
        }

        $this->eachAffectedSubmissionFileIdBatch(
            $cutoffDate,
            fn (array $submissionFileIds) => $this->processBatch($submissionFileIds, $cutoffDate)
        );

        if ($this->correctedCount || $this->skippedCount) {
            $this->_installer->log(
                sprintf(
                    'I12347_FixRevisionUploadLogData: corrected %d revision upload log entries; '
                        . 'left %d unchanged, either because they were already correct or because neither the paired '
                        . 'metadata entry nor the revision chain could confirm which file they should name.',
                    $this->correctedCount,
                    $this->skippedCount
                )
            );
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    /**
     * Determine the date when the buggy code (>= 3.4.0) was first installed.
     *
     * Data created before this version was installed is correct and must not be
     * modified by this migration.
     *
     * @return ?string The earliest date_installed among versions >= 3.4.0, or null if no
     *                 such version exists (meaning the buggy code never ran).
     */
    private function getBugIntroductionDate(): ?string
    {
        $product = Application::getName();

        return DB::table('versions')
            ->where('product_type', 'core')
            ->where('product', $product)
            ->whereRaw('major * 1000 + minor * 100 + revision * 10 + build >= ?', [3400])
            ->min('date_installed');
    }

    /**
     * Walk the submission files that have at least one revision upload log entry
     * created on or after the cutoff date, in batches of self::CHUNK_SIZE.
     *
     * Paged by assoc_id rather than by offset: event_type and date_logged are not indexed,
     * so an offset would re-walk the whole event_log_assoc range on every page.
     *
     * @param callable(int[]): void $callback
     */
    private function eachAffectedSubmissionFileIdBatch(string $cutoffDate, callable $callback): void
    {
        $lastSubmissionFileId = 0;

        while (true) {
            $submissionFileIds = DB::table('event_log')
                ->where('assoc_type', self::ASSOC_TYPE_SUBMISSION_FILE)
                ->where('event_type', self::EVENT_TYPE_REVISION_UPLOAD)
                ->where('date_logged', '>=', $cutoffDate)
                ->where('assoc_id', '>', $lastSubmissionFileId)
                ->distinct()
                ->orderBy('assoc_id')
                ->limit(self::CHUNK_SIZE)
                ->pluck('assoc_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($submissionFileIds)) {
                return;
            }

            $callback($submissionFileIds);

            $lastSubmissionFileId = end($submissionFileIds);
        }
    }

    /**
     * Compute and persist the corrections for one batch of submission files.
     *
     * @param int[] $submissionFileIds
     */
    private function processBatch(array $submissionFileIds, string $cutoffDate): void
    {
        $revisionChains = $this->fetchRevisionChains($submissionFileIds);
        $logEntries = $this->fetchLogEntries($submissionFileIds);
        $logFilenames = $this->fetchLogFilenames($submissionFileIds);
        $currentNames = $this->fetchCurrentNames($submissionFileIds);

        $corrections = [];

        foreach ($submissionFileIds as $submissionFileId) {
            $chain = $revisionChains->get($submissionFileId, collect())
                ->pluck('file_id')
                ->map(fn ($fileId) => (int) $fileId)
                ->all();

            $rows = $logEntries->get($submissionFileId, collect())
                ->map(fn (object $row) => (object) [
                    'logId' => (int) $row->log_id,
                    'eventType' => (int) $row->event_type,
                    'dateLogged' => (string) $row->date_logged,
                    'fileId' => $row->file_id === null ? null : (int) $row->file_id,
                ])
                ->values()
                ->all();

            $corrections += $this->computeGroupCorrections(
                $rows,
                $chain,
                $logFilenames,
                $currentNames->get($submissionFileId, collect())->pluck('setting_value', 'locale')->all(),
                $cutoffDate
            );
        }

        $this->writeCorrections($corrections);
    }

    /**
     * Work out which revision upload entries of a single submission file can be corrected,
     * and to what.
     *
     * Two rules are applied, in order. Both refuse to act unless the revision chain confirms
     * the correction, which also means neither rule matches data that is already correct.
     *
     * Entries logged before the cutoff date were written by the pre-3.4.0 code, which
     * recorded the file correctly. They stay in $rows so that sequencing and pairing still
     * work, but they are never corrected.
     *
     * @param object[] $rows              Log entries in chronological order
     * @param int[]    $chain             file_ids in revision order
     * @param Collection $logFilenames    All filename settings of this batch, keyed by log_id
     * @param array<string,string> $currentNames Current submission file name, keyed by locale
     *
     * @return array<int,array{fileId: int, filenames: array<string,string>}> Keyed by log_id
     */
    private function computeGroupCorrections(array $rows, array $chain, Collection $logFilenames, array $currentNames, string $cutoffDate): array
    {
        // A single revision means there was only the initial upload; nothing to correct.
        if (count($chain) < 2) {
            return [];
        }

        // Map each file to the one that replaced it in the revision chain.
        $nextFileIdMap = [];
        for ($i = 0; $i < count($chain) - 1; $i++) {
            $nextFileIdMap[$chain[$i]] = $chain[$i + 1];
        }

        $corrections = $this->applyPairedFileEditRule($rows, $nextFileIdMap, $logFilenames, $cutoffDate);

        if (empty($corrections)) {
            $corrections = $this->applyRevisionChainRule($rows, $chain, $logFilenames, $currentNames, $cutoffDate);
        }

        // Anything whose logged file is still mid-chain looks wrong but could not be
        // confirmed; report it rather than guessing.
        $candidates = 0;
        foreach ($rows as $row) {
            if (
                $row->eventType === self::EVENT_TYPE_REVISION_UPLOAD
                && $row->dateLogged >= $cutoffDate
                && isset($nextFileIdMap[$row->fileId])
            ) {
                $candidates++;
            }
        }

        $this->correctedCount += count($corrections);
        $this->skippedCount += max(0, $candidates - count($corrections));

        return $corrections;
    }

    /**
     * Rule 1: take the correct data from the metadata entry that pairs with the upload.
     *
     * The upload wizard writes a revision upload entry when the file itself is stored, then
     * a file edit entry when the metadata step is saved a moment later. By that point the
     * submission file has been re-read from the database, so the file edit entry records the
     * revision that was actually uploaded. The revision chain is used to confirm the pairing
     * before anything is copied across.
     *
     * @param object[] $rows
     * @param array<int,int> $nextFileIdMap
     *
     * @return array<int,array{fileId: int, filenames: array<string,string>}>
     */
    private function applyPairedFileEditRule(array $rows, array $nextFileIdMap, Collection $logFilenames, string $cutoffDate): array
    {
        $corrections = [];

        foreach ($rows as $index => $row) {
            if ($row->eventType !== self::EVENT_TYPE_REVISION_UPLOAD || $row->dateLogged < $cutoffDate) {
                continue;
            }

            // Not a key in the map means the entry already points at the newest revision,
            // or at a file that is no longer part of the chain. Either way, leave it alone.
            $expectedFileId = $nextFileIdMap[$row->fileId] ?? null;
            if ($expectedFileId === null) {
                continue;
            }

            $paired = $rows[$index + 1] ?? null;
            if (!$paired || $paired->eventType !== self::EVENT_TYPE_FILE_EDIT) {
                continue;
            }

            // The pairing only counts if the metadata entry names the revision that the
            // chain says followed this upload.
            if ($paired->fileId !== $expectedFileId) {
                continue;
            }

            $filenames = $this->filenamesOf($logFilenames, $paired->logId);
            if (empty($filenames)) {
                continue;
            }

            $corrections[$row->logId] = ['fileId' => $expectedFileId, 'filenames' => $filenames];
        }

        return $corrections;
    }

    /**
     * Rule 2: shift along the revision chain, but only for an exact match of a buggy shape.
     *
     * Used for revision uploads that have no metadata entry to pair with, such as those
     * created by the native XML import, the JATS and body text repositories, or the REST API.
     * The bug produced two distinct signatures, depending on whether the caller reloaded the
     * submission file between uploads; both are recognised, and nothing else is. Correct data,
     * and data this migration has already corrected, matches neither.
     *
     * @param object[] $rows
     * @param int[] $chain
     * @param array<string,string> $currentNames
     *
     * @return array<int,array{fileId: int, filenames: array<string,string>}>
     */
    private function applyRevisionChainRule(array $rows, array $chain, Collection $logFilenames, array $currentNames, string $cutoffDate): array
    {
        // Keep the position within $rows so the trailing metadata entry can still be found.
        $revisionUploads = [];
        foreach ($rows as $index => $row) {
            if ($row->eventType === self::EVENT_TYPE_REVISION_UPLOAD && $row->fileId !== null) {
                $revisionUploads[] = ['index' => $index, 'row' => $row];
            }
        }

        $count = count($revisionUploads);

        // Each entry is corrected to the file that followed it, so the chain has to be at
        // least one longer than the number of entries.
        if ($count === 0 || count($chain) < $count + 1) {
            return [];
        }

        $loggedFileIds = array_map(fn (array $entry) => $entry['row']->fileId, $revisionUploads);

        // Signature A: one edit() per freshly loaded submission file, so every entry logs the
        // file the object held immediately before its own upload.
        if ($loggedFileIds === array_slice($chain, 0, $count)) {
            return $this->shiftAlongChain(
                $revisionUploads,
                $chain,
                $cutoffDate,
                // The next revision upload entry carries the name of the file this one should
                // have recorded. For the final entry, fall back to the metadata entry that
                // follows it, and only then to the submission file's current name.
                fn (int $position, array $entry): array => (isset($revisionUploads[$position + 1])
                    ? $this->filenamesOf($logFilenames, $revisionUploads[$position + 1]['row']->logId)
                    : $this->trailingFileEditFilenames($rows, $entry['index'], $logFilenames)) ?: $currentNames
            );
        }

        // Signature B: several edit() calls against a submission file that is never reloaded,
        // as in NativeXmlSubmissionFileFilter's revision loop. edit() only ever mutates its
        // clone, so the caller's object keeps the first revision and every entry logs it. A
        // run of two or more identical ids cannot occur in correct data, where each entry
        // names a different file.
        if ($count >= 2 && $loggedFileIds === array_fill(0, $count, $chain[0])) {
            return $this->shiftAlongChain(
                $revisionUploads,
                $chain,
                $cutoffDate,
                // The name did not advance either, so the entry already carries the right one.
                fn (int $position, array $entry): array => $this->filenamesOf($logFilenames, $entry['row']->logId) ?: $currentNames
            );
        }

        return [];
    }

    /**
     * Correct each revision upload entry to the file that followed the one it logged.
     *
     * @param array<int,array{index: int, row: object}> $revisionUploads
     * @param int[] $chain
     * @param callable(int, array{index: int, row: object}): array<string,string> $filenamesFor
     *
     * @return array<int,array{fileId: int, filenames: array<string,string>}>
     */
    private function shiftAlongChain(array $revisionUploads, array $chain, string $cutoffDate, callable $filenamesFor): array
    {
        $corrections = [];

        foreach ($revisionUploads as $position => $entry) {
            if ($entry['row']->dateLogged < $cutoffDate) {
                continue;
            }

            $filenames = $filenamesFor($position, $entry);

            if (empty($filenames)) {
                continue;
            }

            $corrections[$entry['row']->logId] = [
                'fileId' => $chain[$position + 1],
                'filenames' => $filenames,
            ];
        }

        return $corrections;
    }

    /**
     * Filenames of the metadata entry immediately following the given position, if any.
     *
     * @param object[] $rows
     *
     * @return array<string,string>
     */
    private function trailingFileEditFilenames(array $rows, int $index, Collection $logFilenames): array
    {
        $next = $rows[$index + 1] ?? null;

        if (!$next || $next->eventType !== self::EVENT_TYPE_FILE_EDIT) {
            return [];
        }

        return $this->filenamesOf($logFilenames, $next->logId);
    }

    /**
     * @return array<string,string> Filename settings of a log entry, keyed by locale
     */
    private function filenamesOf(Collection $logFilenames, int $logId): array
    {
        $rows = $logFilenames->get($logId);

        return $rows ? $rows->pluck('setting_value', 'locale')->all() : [];
    }

    /**
     * Fetch the revision chains of the given submission files.
     *
     * @param int[] $submissionFileIds
     *
     * @return Collection Keyed by submission_file_id
     */
    private function fetchRevisionChains(array $submissionFileIds): Collection
    {
        return DB::table('submission_file_revisions')
            ->whereIn('submission_file_id', $submissionFileIds)
            ->orderBy('submission_file_id')
            ->orderBy('revision_id')
            ->get(['submission_file_id', 'file_id'])
            ->groupBy('submission_file_id');
    }

    /**
     * Fetch the file related log entries of the given submission files, with their fileId
     * setting, in chronological order.
     *
     * @param int[] $submissionFileIds
     *
     * @return Collection Keyed by submission_file_id
     */
    private function fetchLogEntries(array $submissionFileIds): Collection
    {
        return DB::table('event_log AS el')
            ->leftJoin('event_log_settings AS els', function ($join) {
                $join->on('els.log_id', '=', 'el.log_id')
                    ->where('els.setting_name', '=', 'fileId');
            })
            ->whereIn('el.assoc_id', $submissionFileIds)
            ->where('el.assoc_type', self::ASSOC_TYPE_SUBMISSION_FILE)
            ->whereIn('el.event_type', [
                self::EVENT_TYPE_FILE_UPLOAD,
                self::EVENT_TYPE_REVISION_UPLOAD,
                self::EVENT_TYPE_FILE_EDIT,
            ])
            // date_logged only resolves to the second, and both rules depend on the order of
            // the entries, so log_id breaks ties and keeps the sequence deterministic.
            ->orderBy('el.assoc_id')
            ->orderBy('el.date_logged')
            ->orderBy('el.log_id')
            ->get([
                'el.log_id',
                'el.assoc_id',
                'el.event_type',
                'el.date_logged',
                'els.setting_value AS file_id',
            ])
            ->groupBy('assoc_id');
    }

    /**
     * Fetch the filename settings of the file related log entries of the given submission files.
     *
     * @param int[] $submissionFileIds
     *
     * @return Collection Keyed by log_id
     */
    private function fetchLogFilenames(array $submissionFileIds): Collection
    {
        return DB::table('event_log AS el')
            ->join('event_log_settings AS els', 'els.log_id', '=', 'el.log_id')
            ->whereIn('el.assoc_id', $submissionFileIds)
            ->where('el.assoc_type', self::ASSOC_TYPE_SUBMISSION_FILE)
            ->whereIn('el.event_type', [
                self::EVENT_TYPE_FILE_UPLOAD,
                self::EVENT_TYPE_REVISION_UPLOAD,
                self::EVENT_TYPE_FILE_EDIT,
            ])
            ->where('els.setting_name', 'filename')
            ->get(['el.log_id', 'els.locale', 'els.setting_value'])
            ->groupBy('log_id');
    }

    /**
     * Fetch the current names of the given submission files.
     *
     * @param int[] $submissionFileIds
     *
     * @return Collection Keyed by submission_file_id
     */
    private function fetchCurrentNames(array $submissionFileIds): Collection
    {
        return DB::table('submission_file_settings')
            ->whereIn('submission_file_id', $submissionFileIds)
            ->where('setting_name', 'name')
            ->get(['submission_file_id', 'locale', 'setting_value'])
            ->groupBy('submission_file_id');
    }

    /**
     * Persist the corrections of one batch.
     *
     * The fileId and the filenames of an entry are always written together, inside a single
     * transaction, so that an entry can never be left naming one file while pointing at another.
     *
     * @param array<int,array{fileId: int, filenames: array<string,string>}> $corrections
     */
    private function writeCorrections(array $corrections): void
    {
        if (empty($corrections)) {
            return;
        }

        DB::transaction(function () use ($corrections) {
            $this->updateFileIds($corrections);
            $this->replaceFilenames($corrections);
        });
    }

    /**
     * @param array<int,array{fileId: int, filenames: array<string,string>}> $corrections
     */
    private function updateFileIds(array $corrections): void
    {
        foreach (array_chunk($corrections, self::CHUNK_SIZE, true) as $chunk) {
            $cases = [];
            $logIds = [];

            foreach ($chunk as $logId => $correction) {
                // Both values are integer cast, so the statement cannot be injected into.
                $cases[] = sprintf('WHEN %d THEN \'%d\'', (int) $logId, (int) $correction['fileId']);
                $logIds[] = (int) $logId;
            }

            $caseSql = implode(' ', $cases);
            $logIdList = implode(',', $logIds);

            DB::statement(
                "UPDATE event_log_settings SET setting_value = CASE log_id {$caseSql} ELSE setting_value END WHERE log_id IN ({$logIdList}) AND setting_name = 'fileId'"
            );
        }
    }

    /**
     * Replace the filename settings of the corrected entries.
     *
     * Deleting first also clears locales that the corrected entry no longer has. This is only
     * safe because writeCorrections() wraps it in a transaction together with the re-insert.
     *
     * @param array<int,array{fileId: int, filenames: array<string,string>}> $corrections
     */
    private function replaceFilenames(array $corrections): void
    {
        foreach (array_chunk(array_keys($corrections), self::CHUNK_SIZE) as $chunk) {
            DB::table('event_log_settings')
                ->whereIn('log_id', $chunk)
                ->where('setting_name', 'filename')
                ->delete();
        }

        $inserts = [];
        foreach ($corrections as $logId => $correction) {
            foreach ($correction['filenames'] as $locale => $name) {
                $inserts[] = [
                    'log_id' => $logId,
                    'setting_name' => 'filename',
                    'locale' => $locale,
                    'setting_value' => $name,
                ];
            }
        }

        foreach (array_chunk($inserts, self::INSERT_CHUNK_SIZE) as $chunk) {
            DB::table('event_log_settings')->insert($chunk);
        }
    }
}
