<?php

/**
 * @file classes/statistics/PKPTemporaryItemRequestsDAO.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTemporaryItemRequestsDAO
 *
 * @ingroup statistics
 *
 * @brief Operations for retrieving and adding unique item (submission) requests (primary files downloads).
 */

namespace PKP\statistics;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\db\DAORegistry;

class PKPTemporaryItemRequestsDAO
{
    /**
     * The name of the table.
     * This table contains all primary files downloads.
     */
    public string $table = 'usage_stats_unique_item_requests_temporary_records';

    /**
     * Add the passed usage statistic record.
     */
    public function insert(object $entryData, int $lineNumber, string $loadId): void
    {
        $insertData = $this->getInsertData($entryData);
        $insertData['line_number'] = $lineNumber;
        $insertData['load_id'] = $loadId;

        DB::table($this->table)->insert($insertData);
    }

    /**
     * Get Laravel optimized array of data to insert into the table based on the log entry
     */
    protected function getInsertData(object $entryData): array
    {
        return [
            'date' => $entryData->time,
            'ip' => $entryData->ip,
            'user_agent' => substr($entryData->userAgent, 0, 255),
            'context_id' => $entryData->contextId,
            'submission_id' => $entryData->submissionId,
            'representation_id' => $entryData->representationId,
            'submission_file_id' => $entryData->submissionFileId,
            'assoc_type' => $entryData->assocType,
            'file_type' => $entryData->fileType,
            'country' => !empty($entryData->country) ? $entryData->country : '',
            'region' => !empty($entryData->region) ? $entryData->region : '',
            'city' => !empty($entryData->city) ? $entryData->city : '',
        ];
    }

    /**
     * Delete all temporary records associated
     * with the passed load id.
     */
    public function deleteByLoadId(string $loadId): void
    {
        DB::table($this->table)->where('load_id', '=', $loadId)->delete();
    }

    /**
     * Remove unique clicks
     * If multiple transactions represent the same item and occur in the same user-sessions, only one unique activity MUST be counted for that item.
     * Unique item is a submission.
     * A user session is defined by the combination of IP address + user agent + transaction date + hour of day.
     * Only the last unique activity will be retained (and thus counted), all the other will be removed.
     *
     * See https://www.projectcounter.org/code-of-practice-five-sections/7-processing-rules-underlying-counter-reporting-data/#counting
     */
    public function compileUniqueClicks(string $loadId): void
    {
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            DB::statement(
                "
                DELETE FROM {$this->table} usur
                WHERE EXISTS (
                    SELECT * FROM (
                        SELECT 1 FROM {$this->table} usurt
                        WHERE usur.load_id = ? AND usurt.load_id = usur.load_id AND
                        usurt.context_id = usur.context_id AND
                        usurt.ip = usur.ip AND
                        usurt.user_agent = usur.user_agent AND
                        usurt.submission_id = usur.submission_id AND
                        EXTRACT(HOUR FROM usurt.date) = EXTRACT(HOUR FROM usur.date) AND
                        usur.line_number < usurt.line_number
                    ) AS tmp
                )
                ",
                [$loadId]
            );
        } else {
            DB::statement(
                "
                DELETE FROM usur USING {$this->table} usur
                INNER JOIN {$this->table} usurt ON (
                    usurt.load_id = usur.load_id AND
                    usurt.context_id = usur.context_id AND
                    usurt.ip = usur.ip AND
                    usurt.user_agent = usur.user_agent AND
                    usurt.submission_id = usur.submission_id
                )
                WHERE usur.load_id = ? AND TIMESTAMPDIFF(HOUR, usur.date, usurt.date) = 0 AND usur.line_number < usurt.line_number
                ",
                [$loadId]
            );
        }
    }

    /**
     * Load unique COUNTER item (submission) requests (primary files downloads)
     */
    public function compileCounterSubmissionDailyMetrics(string $loadId): void
    {
        // construct metric_requests_unique upsert
        // assoc_type should always be Application::ASSOC_TYPE_SUBMISSION_FILE, but include the condition however
        $metricRequestsUniqueUpsertSql = "
            INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
            SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, 0 as metric_investigations, 0 as metric_investigations_unique, 0 as metric_requests, count(*) as metric
                FROM {$this->table}
                WHERE load_id = ? AND assoc_type = ?
                GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
            ";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $metricRequestsUniqueUpsertSql .= '
                ON CONFLICT ON CONSTRAINT msd_uc_load_id_context_id_submission_id_date DO UPDATE
                SET metric_requests_unique = excluded.metric_requests_unique;
                ';
        } else {
            $metricRequestsUniqueUpsertSql .= '
                ON DUPLICATE KEY UPDATE metric_requests_unique = metric;
                ';
        }
        DB::statement($metricRequestsUniqueUpsertSql, [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE]);
    }

    /**
     * Load unique institutional COUNTER item (submission) requests (primary files downloads)
     */
    public function compileCounterSubmissionInstitutionDailyMetrics(string $loadId): void
    {
        // construct metric_requests_unique upsert
        // assoc_type should always be Application::ASSOC_TYPE_SUBMISSION_FILE, but include the condition however
        $metricRequestsUniqueUpsertSql = "
            INSERT INTO metrics_counter_submission_institution_daily (load_id, context_id, submission_id, date, institution_id, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                SELECT * FROM (
                    SELECT usur.load_id, usur.context_id, usur.submission_id, DATE(usur.date) as date, usi.institution_id, 0 as metric_investigations, 0 as metric_investigations_unique, 0 as metric_requests, count(*) as metric
                    FROM {$this->table} usur
                    JOIN usage_stats_institution_temporary_records usi on (usi.load_id = usur.load_id AND usi.line_number = usur.line_number)
                    WHERE usur.load_id = ? AND usur.assoc_type = ? AND usi.institution_id = ?
                    GROUP BY usur.load_id, usur.context_id, usur.submission_id, DATE(usur.date), usi.institution_id) AS t
            ";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $metricRequestsUniqueUpsertSql .= '
                ON CONFLICT ON CONSTRAINT msid_uc_load_id_context_id_submission_id_institution_id_date DO UPDATE
                SET metric_requests_unique = excluded.metric_requests_unique;
                ';
        } else {
            $metricRequestsUniqueUpsertSql .= '
                ON DUPLICATE KEY UPDATE metric_requests_unique = metric;
                ';
        }

        /** @var TemporaryInstitutionsDAO */
        $temporaryInstitutionsDAO = DAORegistry::getDAO('TemporaryInstitutionsDAO');
        $institutionIds = $temporaryInstitutionsDAO->getInstitutionIdsByLoadId($loadId);
        foreach ($institutionIds as $institutionId) {
            DB::statement($metricRequestsUniqueUpsertSql, [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE, (int) $institutionId]);
        }
    }
}
