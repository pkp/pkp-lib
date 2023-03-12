<?php

/**
 * @file classes/statistics/PKPTemporaryItemInvestigationsDAO.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTemporaryItemInvestigationsDAO
 * @ingroup statistics
 *
 * @brief Operations for retrieving and adding unique item (submission) investigations (abstract, primary and supp file views).
 */

namespace PKP\statistics;

use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\db\DAORegistry;

class PKPTemporaryItemInvestigationsDAO
{
    /**
     * The name of the table.
     * This table contains all usage (clicks) for an item (submission),
     * considering abstract, primary and supp file views.
     */
    public string $table = 'usage_stats_unique_item_investigations_temporary_records';

    /**
     * Add the passed usage statistic record.
     */
    public function insert(object $entryData, int $lineNumber, string $loadId): void
    {
        DB::table($this->table)->insert([
            'date' => $entryData->time,
            'ip' => $entryData->ip,
            'user_agent' => substr($entryData->userAgent, 0, 255),
            'line_number' => $lineNumber,
            'context_id' => $entryData->contextId,
            'submission_id' => $entryData->submissionId,
            'representation_id' => $entryData->representationId,
            'submission_file_id' => $entryData->submissionFileId,
            'assoc_type' => $entryData->assocType,
            'file_type' => $entryData->fileType,
            'country' => !empty($entryData->country) ? $entryData->country : '',
            'region' => !empty($entryData->region) ? $entryData->region : '',
            'city' => !empty($entryData->city) ? $entryData->city : '',
            'load_id' => $loadId,
        ]);
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
     * Remove Unique Clicks
     * If multiple transactions represent the same item and occur in the same user-sessions, only one unique activity MUST be counted for that item.
     * Unique item is a submission.
     * A user session is defined by the combination of IP address + user agent + transaction date + hour of day.
     * Only the last unique activity will be retained (and thus counted), all the other will be removed.
     *
     * See https://www.projectcounter.org/code-of-practice-five-sections/7-processing-rules-underlying-counter-reporting-data/#counting
     */
    public function compileUniqueClicks(): void
    {
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            DB::statement("DELETE FROM {$this->table} usui WHERE EXISTS (SELECT * FROM (SELECT 1 FROM {$this->table} usuit WHERE usuit.load_id = usui.load_id AND usuit.ip = usui.ip AND usuit.user_agent = usui.user_agent AND usuit.context_id = usui.context_id AND usuit.submission_id = usui.submission_id AND EXTRACT(HOUR FROM usuit.date) = EXTRACT(HOUR FROM usui.date) AND usui.line_number < usuit.line_number) AS tmp)");
        } else {
            DB::statement("
                DELETE FROM usui USING {$this->table} usui
                INNER JOIN {$this->table} usuit ON (usuit.load_id = usui.load_id AND usuit.ip = usui.ip AND usuit.user_agent = usui.user_agent AND usuit.context_id = usui.context_id AND usuit.submission_id = usui.submission_id)
                WHERE TIMESTAMPDIFF(HOUR, usui.date, usuit.date) = 0 AND usui.line_number < usuit.line_number
            ");
        }
    }

    /**
     * Load unique geographical usage on the submission level
     */
    public function compileSubmissionGeoDailyMetrics(string $loadId): void
    {
        // construct metric_unique upsert
        $metricUniqueUpsertSql = "
            INSERT INTO metrics_submission_geo_daily (load_id, context_id, submission_id, date, country, region, city, metric, metric_unique)
            SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, country, region, city, 0 as metric, count(*) as metric_unique_tmp
                FROM {$this->table}
                WHERE load_id = ? AND submission_id IS NOT NULL AND (country <> '' OR region <> '' OR city <> '')
                GROUP BY load_id, context_id, submission_id, DATE(date), country, region, city) AS t
            ";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $metricUniqueUpsertSql .= '
                ON CONFLICT ON CONSTRAINT msgd_uc_load_context_submission_c_r_c_date DO UPDATE
                SET metric_unique = excluded.metric_unique;
                ';
        } else {
            $metricUniqueUpsertSql .= '
                ON DUPLICATE KEY UPDATE metric_unique = metric_unique_tmp;
                ';
        }
        DB::statement($metricUniqueUpsertSql, [$loadId]);
    }

    /**
     * Load unique COUNTER item (submission) investigations
     */
    public function compileCounterSubmissionDailyMetrics(string $loadId): void
    {
        // construct metric_investigations_unique upsert
        $metricInvestigationsUniqueUpsertSql = "
            INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
            SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, 0 as metric_investigations, count(*) as metric, 0 as metric_requests, 0 as metric_requests_unique
                FROM {$this->table}
                WHERE load_id = ? AND submission_id IS NOT NULL
                GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
            ";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $metricInvestigationsUniqueUpsertSql .= '
                ON CONFLICT ON CONSTRAINT msd_uc_load_id_context_id_submission_id_date DO UPDATE
                SET metric_investigations_unique = excluded.metric_investigations_unique;
                ';
        } else {
            $metricInvestigationsUniqueUpsertSql .= '
                ON DUPLICATE KEY UPDATE metric_investigations_unique = metric;
                ';
        }
        DB::statement($metricInvestigationsUniqueUpsertSql, [$loadId]);
    }

    /**
     * Load unique institutional COUNTER item (submission) investigations
     */
    public function compileCounterSubmissionInstitutionDailyMetrics(string $loadId): void
    {
        // construct metric_investigations_unique upsert
        $metricInvestigationsUniqueUpsertSql = "
            INSERT INTO metrics_counter_submission_institution_daily (load_id, context_id, submission_id, date, institution_id, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
            SELECT * FROM (
                SELECT usui.load_id, usui.context_id, usui.submission_id, DATE(usui.date) as date, usi.institution_id, 0 as metric_investigations, count(*) as metric, 0 as metric_requests, 0 as metric_requests_unique
                FROM {$this->table} usui
                JOIN usage_stats_institution_temporary_records usi on (usi.load_id = usui.load_id AND usi.line_number = usui.line_number)
                WHERE usui.load_id = ? AND submission_id IS NOT NULL AND usi.institution_id = ?
                GROUP BY usui.load_id, usui.context_id, usui.submission_id, DATE(usui.date), usi.institution_id) AS t
            ";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $metricInvestigationsUniqueUpsertSql .= '
                ON CONFLICT ON CONSTRAINT msid_uc_load_id_context_id_submission_id_institution_id_date DO UPDATE
                SET metric_investigations_unique = excluded.metric_investigations_unique;
                ';
        } else {
            $metricInvestigationsUniqueUpsertSql .= '
                ON DUPLICATE KEY UPDATE metric_investigations_unique = metric;
                ';
        }

        /** @var TemporaryInstitutionsDAO */
        $temporaryInstitutionsDAO = DAORegistry::getDAO('TemporaryInstitutionsDAO');
        $institutionIds = $temporaryInstitutionsDAO->getInstitutionIdsByLoadId($loadId);
        foreach ($institutionIds as $institutionId) {
            DB::statement($metricInvestigationsUniqueUpsertSql, [$loadId, (int) $institutionId]);
        }
    }
}
