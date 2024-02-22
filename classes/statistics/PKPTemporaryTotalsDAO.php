<?php

/**
 * @file classes/statistics/PKPTemporaryTotalsDAO.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTemporaryTotalsDAO
 *
 * @ingroup statistics
 *
 * @brief Operations for retrieving and adding total usage.
 *
 * It considers:
 * context index page views,
 * submission abstract, primary and supp file views,
 * geo submission usage,
 * COUNTER submission stats.
 */

namespace PKP\statistics;

use APP\core\Application;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\db\DAORegistry;

abstract class PKPTemporaryTotalsDAO
{
    /**
     * The name of the table. This table contains all usage events.
     */
    public string $table = 'usage_stats_total_temporary_records';

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
            'canonical_url' => $entryData->canonicalUrl,
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
     * Remove Double Clicks according to COUNTER guidelines
     * Remove the potential of over-counting which could occur when a user clicks the same link multiple times.
     * Double-clicks, i.e. two clicks in succession, on a link by the same user within a 30-second period MUST be counted as one action.
     * When two actions are made for the same URL within 30 seconds the first request MUST be removed and the second retained.
     * A user is identified by IP address combined with the browser’s user-agent.
     *
     * See https://www.projectcounter.org/code-of-practice-five-sections/7-processing-rules-underlying-counter-reporting-data/#doubleclick
     */
    public function removeDoubleClicks(string $loadId, int $counterDoubleClickTimeFilter): void
    {
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            DB::statement(
                "
                DELETE FROM {$this->table} ust
                WHERE EXISTS (
                    SELECT * FROM (
                        SELECT 1 FROM {$this->table} ustt
                        WHERE ust.load_id = ? AND ustt.load_id = ust.load_id AND
                        ustt.context_id = ust.context_id AND
                        ustt.ip = ust.ip AND ustt.user_agent = ust.user_agent AND ustt.canonical_url = ust.canonical_url AND
                        EXTRACT(EPOCH FROM (ustt.date - ust.date)) < ? AND
                        EXTRACT(EPOCH FROM (ustt.date - ust.date)) > 0 AND
                        ust.line_number < ustt.line_number) AS tmp
                )
                ",
                [$loadId, $counterDoubleClickTimeFilter]
            );
        } else {
            DB::statement(
                "
                DELETE FROM ust USING {$this->table} ust
                INNER JOIN {$this->table} ustt ON (
                    ustt.load_id = ust.load_id AND
                    ustt.context_id = ust.context_id AND
                    ustt.ip = ust.ip AND
                    ustt.user_agent = ust.user_agent AND
                    ustt.canonical_url = ust.canonical_url
                )
                WHERE ust.load_id = ? AND
                    TIMESTAMPDIFF(SECOND, ust.date, ustt.date) < ? AND
                    TIMESTAMPDIFF(SECOND, ust.date, ustt.date) > 0 AND
                    ust.line_number < ustt.line_number
                ",
                [$loadId, $counterDoubleClickTimeFilter]
            );
        }
    }

    /**
     * Load usage for context index pages
     */
    public function compileContextMetrics(string $loadId): void
    {
        $date = DateTimeImmutable::createFromFormat('Ymd', substr($loadId, -12, 8));
        DB::table('metrics_context')->where('load_id', '=', $loadId)->orWhereDate('date', '=', $date)->delete();
        $selectContextMetrics = DB::table($this->table)
            ->select(DB::raw('load_id, context_id, DATE(date) as date, count(*) as metric'))
            ->where('load_id', '=', $loadId)
            ->where('assoc_type', '=', Application::getContextAssocType())
            ->groupBy(DB::raw('load_id, context_id, DATE(date)'));
        DB::table('metrics_context')->insertUsing(['load_id', 'context_id', 'date', 'metric'], $selectContextMetrics);
    }

    /**
     * Load usage for submissions (abstract, primary and supp files)
     */
    public function compileSubmissionMetrics(string $loadId): void
    {
        $date = DateTimeImmutable::createFromFormat('Ymd', substr($loadId, -12, 8));
        DB::table('metrics_submission')->where('load_id', '=', $loadId)->orWhereDate('date', '=', $date)->delete();
        $selectSubmissionMetrics = DB::table($this->table)
            ->select(DB::raw('load_id, context_id, submission_id, assoc_type, DATE(date) as date, count(*) as metric'))
            ->where('load_id', '=', $loadId)
            ->where('assoc_type', '=', Application::ASSOC_TYPE_SUBMISSION)
            ->groupBy(DB::raw('load_id, context_id, submission_id, assoc_type, DATE(date)'));
        DB::table('metrics_submission')->insertUsing(['load_id', 'context_id', 'submission_id', 'assoc_type', 'date', 'metric'], $selectSubmissionMetrics);

        $selectSubmissionFileMetrics = DB::table($this->table)
            ->select(DB::raw('load_id, context_id, submission_id, representation_id, submission_file_id, file_type, assoc_type, DATE(date) as date, count(*) as metric'))
            ->where('load_id', '=', $loadId)
            ->where('assoc_type', '=', Application::ASSOC_TYPE_SUBMISSION_FILE)
            ->groupBy(DB::raw('load_id, context_id, submission_id, representation_id, submission_file_id, file_type, assoc_type, DATE(date)'));
        DB::table('metrics_submission')->insertUsing(['load_id', 'context_id', 'submission_id', 'representation_id', 'submission_file_id', 'file_type', 'assoc_type', 'date', 'metric'], $selectSubmissionFileMetrics);

        $selectSubmissionSuppFileMetrics = DB::table($this->table)
            ->select(DB::raw('load_id, context_id, submission_id, representation_id, submission_file_id, file_type, assoc_type, DATE(date) as date, count(*) as metric'))
            ->where('load_id', '=', $loadId)
            ->where('assoc_type', '=', Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER)
            ->groupBy(DB::raw('load_id, context_id, submission_id, representation_id, submission_file_id, file_type, assoc_type, DATE(date)'));
        DB::table('metrics_submission')->insertUsing(['load_id', 'context_id', 'submission_id', 'representation_id', 'submission_file_id', 'file_type', 'assoc_type', 'date', 'metric'], $selectSubmissionSuppFileMetrics);
    }

    // For the DB tables that contain also the unique metrics, this deletion by loadId is in a separate function,
    // differently to the deletion for the tables above (metrics_context, metrics_issue and metrics_submission)
    // The total metrics will be loaded here (s. load... functions below), unique metrics are loaded in UnsageStatsUnique... classes
    public function deleteSubmissionGeoDailyByLoadId(string $loadId): void
    {
        $date = DateTimeImmutable::createFromFormat('Ymd', substr($loadId, -12, 8));
        DB::table('metrics_submission_geo_daily')->where('load_id', '=', $loadId)->orWhereDate('date', '=', $date)->delete();
    }
    public function deleteCounterSubmissionDailyByLoadId(string $loadId): void
    {
        $date = DateTimeImmutable::createFromFormat('Ymd', substr($loadId, -12, 8));
        DB::table('metrics_counter_submission_daily')->where('load_id', '=', $loadId)->orWhereDate('date', '=', $date)->delete();
    }
    public function deleteCounterSubmissionInstitutionDailyByLoadId(string $loadId): void
    {
        $date = DateTimeImmutable::createFromFormat('Ymd', substr($loadId, -12, 8));
        DB::table('metrics_counter_submission_institution_daily')->where('load_id', '=', $loadId)->orWhereDate('date', '=', $date)->delete();
    }

    /**
     * Load total geographical usage on the submission level
     */
    public function compileSubmissionGeoDailyMetrics(string $loadId): void
    {
        // construct metric upsert
        $metricUpsertSql = "
            INSERT INTO metrics_submission_geo_daily (load_id, context_id, submission_id, date, country, region, city, metric, metric_unique)
            SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, country, region, city, count(*) as metric_tmp, 0 as metric_unique
                FROM {$this->table}
                WHERE load_id = ? AND submission_id IS NOT NULL AND (country <> '' OR region <> '' OR city <> '')
                GROUP BY load_id, context_id, submission_id, DATE(date), country, region, city) AS t
            ";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $metricUpsertSql .= '
                ON CONFLICT ON CONSTRAINT msgd_uc_load_context_submission_c_r_c_date DO UPDATE
                SET metric = excluded.metric;
                ';
        } else {
            $metricUpsertSql .= '
                ON DUPLICATE KEY UPDATE metric = metric_tmp;
                ';
        }
        DB::statement($metricUpsertSql, [$loadId]);
    }

    /**
     * Load total COUNTER submission usage (investigations and requests)
     */
    public function compileCounterSubmissionDailyMetrics(string $loadId): void
    {
        // construct metric_investigations upsert
        $metricInvestigationsUpsertSql = "
            INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
            SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, count(*) as metric, 0 as metric_investigations_unique, 0 as metric_requests, 0 as metric_requests_unique
                FROM {$this->table}
                WHERE load_id = ? AND submission_id IS NOT NULL
                GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
            ";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $metricInvestigationsUpsertSql .= '
                ON CONFLICT ON CONSTRAINT msd_uc_load_id_context_id_submission_id_date DO UPDATE
                SET metric_investigations = excluded.metric_investigations;
                ';
        } else {
            $metricInvestigationsUpsertSql .= '
                ON DUPLICATE KEY UPDATE metric_investigations = metric;
            ';
        }
        DB::statement($metricInvestigationsUpsertSql, [$loadId]);

        // construct metric_requests upsert
        $metricRequestsUpsertSql = "
            INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
            SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, 0 as metric_investigations, 0 as metric_investigations_unique, count(*) as metric, 0 as metric_requests_unique
                FROM {$this->table}
                WHERE load_id = ? AND assoc_type = ?
                GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
            ";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $metricRequestsUpsertSql .= '
                ON CONFLICT ON CONSTRAINT msd_uc_load_id_context_id_submission_id_date DO UPDATE
                SET metric_requests = excluded.metric_requests;
                ';
        } else {
            $metricRequestsUpsertSql .= '
                ON DUPLICATE KEY UPDATE metric_requests = metric;
            ';
        }
        DB::statement($metricRequestsUpsertSql, [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE]);
    }

    /**
     * Load total institutional COUNTER submission usage (investigations and requests)
     */
    public function compileCounterSubmissionInstitutionDailyMetrics(string $loadId): void
    {
        // construct metric_investigations upsert
        $metricInvestigationsUpsertSql = "
            INSERT INTO metrics_counter_submission_institution_daily (load_id, context_id, submission_id, date, institution_id, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
            SELECT * FROM (
                SELECT ustt.load_id, ustt.context_id, ustt.submission_id, DATE(ustt.date) as date, usit.institution_id, count(*) as metric, 0 as metric_investigations_unique, 0 as metric_requests, 0 as metric_requests_unique
                FROM {$this->table} ustt
                JOIN usage_stats_institution_temporary_records usit on (usit.load_id = ustt.load_id AND usit.line_number = ustt.line_number)
                WHERE ustt.load_id = ? AND submission_id IS NOT NULL AND usit.institution_id = ?
                GROUP BY ustt.load_id, ustt.context_id, ustt.submission_id, DATE(ustt.date), usit.institution_id) AS t
            ";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $metricInvestigationsUpsertSql .= '
                ON CONFLICT ON CONSTRAINT msid_uc_load_id_context_id_submission_id_institution_id_date DO UPDATE
                SET metric_investigations = excluded.metric_investigations;
                ';
        } else {
            $metricInvestigationsUpsertSql .= '
                ON DUPLICATE KEY UPDATE metric_investigations = metric;
                ';
        }

        // construct metric_requests upsert
        $metricRequestsUpsertSql = "
            INSERT INTO metrics_counter_submission_institution_daily (load_id, context_id, submission_id, date, institution_id, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
            SELECT * FROM (
                SELECT ustt.load_id, ustt.context_id, ustt.submission_id, DATE(ustt.date) as date, usit.institution_id, 0 as metric_investigations, 0 as metric_investigations_unique, count(*) as metric, 0 as metric_requests_unique
                FROM {$this->table} ustt
                JOIN usage_stats_institution_temporary_records usit on (usit.load_id = ustt.load_id AND usit.line_number = ustt.line_number)
                WHERE ustt.load_id = ? AND ustt.assoc_type = ? AND usit.institution_id = ?
                GROUP BY ustt.load_id, ustt.context_id, ustt.submission_id, DATE(ustt.date), usit.institution_id) AS t
            ";
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            $metricRequestsUpsertSql .= '
                ON CONFLICT ON CONSTRAINT msid_uc_load_id_context_id_submission_id_institution_id_date DO UPDATE
                SET metric_requests = excluded.metric_requests;
                ';
        } else {
            $metricRequestsUpsertSql .= '
                ON DUPLICATE KEY UPDATE metric_requests = metric;
            ';
        }

        /** @var TemporaryInstitutionsDAO */
        $temporaryInstitutionsDAO = DAORegistry::getDAO('TemporaryInstitutionsDAO');
        $institutionIds = $temporaryInstitutionsDAO->getInstitutionIdsByLoadId($loadId);
        foreach ($institutionIds as $institutionId) {
            DB::statement($metricInvestigationsUpsertSql, [$loadId, (int) $institutionId]);
            DB::statement($metricRequestsUpsertSql, [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE, (int) $institutionId]);
        }
    }
}
