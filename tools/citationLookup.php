<?php

/**
 * @file tools/citationLookup.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationLookupTool
 *
 * @ingroup tools
 *
 * @brief CLI tool to queue the metadata lookup of existing citations in bulk, and to monitor it,
 *   or to only extract the PIDs from their raw text.
 *   It never deletes or re-imports citations; parseCitations.php re-parses the raw citations.
 */

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\citation\Citation;
use PKP\citation\enum\CitationProcessingStatus;
use PKP\citation\pid\ExtractPidsHelper;
use PKP\context\Context;

require(dirname(__FILE__, 4) . '/tools/bootstrap.php');

class CitationLookupTool extends \PKP\cliTool\CommandLineTool
{
    /** Statuses of citations whose job chain is queued or running */
    protected const IN_FLIGHT = [
        CitationProcessingStatus::QUEUED,
        CitationProcessingStatus::PID_EXTRACTED,
        CitationProcessingStatus::CROSSREF,
        CitationProcessingStatus::OPEN_ALEX,
        CitationProcessingStatus::ORCID,
    ];

    /** Statuses the repair command accepts, name => statuses */
    protected const REPAIRABLE = [
        'failed' => [CitationProcessingStatus::FAILED],
        'queued' => self::IN_FLIGHT,
    ];

    /** Statuses of citations not looked up yet; null for citations from before the lookup existed */
    protected const NOT_LOOKED_UP = [null, CitationProcessingStatus::NOT_PROCESSED];

    /** Citation properties set by ExtractPidsHelper */
    protected const PIDS = ['doi', 'arxiv', 'handle', 'url', 'urn'];

    protected string $command = '';

    /** @var array<string,string|true> Options, name => value, or true for a flag */
    protected array $options = [];

    /** @var Context[] Contexts in scope, by ID */
    protected array $contexts = [];

    /** @var int[] Submissions in scope; empty for all */
    protected array $submissionIds = [];

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        $positional = [];
        foreach ($this->argv as $arg) {
            if (str_starts_with($arg, '--')) {
                [$name, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
                $this->options[$name] = $value;
            } else {
                $positional[] = $arg;
            }
        }

        $this->command = (string) array_shift($positional);
        if (!in_array($this->command, ['status', 'queue', 'repair', 'extract'])) {
            $this->usage();
            exit(1);
        }
        $this->parseScope($positional);
    }

    /**
     * Print command usage information.
     */
    public function usage(): void
    {
        echo "Queue the metadata lookup of existing citations in bulk, and monitor it.\n"
            . "Citations are never deleted or re-imported; use parseCitations.php to re-parse the raw citations.\n"
            . "Contexts with the citation metadata lookup disabled are skipped, except by 'extract'.\n\n"
            . "Usage:\n"
            . "\t{$this->scriptName} status [scope]\n"
            . "\t{$this->scriptName} queue [--limit=N] [scope]\n"
            . "\t{$this->scriptName} repair --status=failed|queued [--force] [scope]\n"
            . "\t{$this->scriptName} extract [scope]\n\n"
            . "Scope (default: all contexts):\n"
            . "\tcontext <context_id> [...]\n"
            . "\tsubmission <submission_id> [...]\n\n"
            . "'status' shows the citations per lookup status, the submissions with lookups still pending,\n"
            . "and the size of the job queue. It changes nothing.\n\n"
            . "'queue' queues the lookup of all citations not looked up yet. Run it once; running it again only\n"
            . "queues what is still left, as queued citations are never picked twice.\n"
            . "--limit=N keeps at most N publications with lookups queued or running, counted across the scope.\n\n"
            . "'repair' queues the lookup again for citations in the given status:\n"
            . "\tfailed     lookups that failed, e.g. during a service outage. If you redispatch the failed jobs\n"
            . "\t           instead (jobs.php failed --redispatch), do not also run this.\n"
            . "\tqueued     lookups stranded as queued or running after the job queue was lost. Refused while the\n"
            . "\t           job queue is not empty, as it would queue them twice, unless --force is given.\n\n"
            . "'extract' only extracts the PIDs (DOI, arXiv ID, handle, URL, URN) from the raw text of the citations\n"
            . "not looked up yet, e.g. after an import with the lookup disabled. It fills only identifiers that are\n"
            . "empty, queues no jobs and requests no external services. The citations stay not looked up, so 'queue'\n"
            . "can still look them up later.\n\n"
            . "The jobs are run by the application's job processing (cron, worker or job runner).\n";
    }

    /**
     * Run the command.
     */
    public function execute(): void
    {
        match ($this->command) {
            'status' => $this->status(),
            'queue' => $this->queue(),
            'repair' => $this->repair(),
            'extract' => $this->extract(),
        };
    }

    /**
     * Read the optional scope: 'context <id> ...' or 'submission <id> ...'.
     */
    protected function parseScope(array $positional): void
    {
        $contextDao = Application::getContextDAO();
        $scope = array_shift($positional);
        if (in_array($scope, ['context', 'submission'])) {
            foreach ($positional as $id) {
                if (!ctype_digit($id)) {
                    $this->exitWithError("Invalid {$scope} ID '{$id}'.");
                }
            }
        }
        $ids = array_map('intval', $positional);

        if ($scope === 'context' && !empty($ids)) {
            foreach ($ids as $contextId) {
                $context = $contextDao->getById($contextId);
                if (!$context) {
                    $this->exitWithError("Unknown context {$contextId}.");
                }
                $this->contexts[$contextId] = $context;
            }
            return;
        }

        if ($scope === 'submission' && !empty($ids)) {
            foreach ($ids as $submissionId) {
                if (!Repo::submission()->exists($submissionId)) {
                    $this->exitWithError("Unknown submission {$submissionId}.");
                }
            }
            $this->submissionIds = $ids;
        } elseif ($scope !== null) {
            $this->usage();
            exit(1);
        }

        $contexts = $contextDao->getAll();
        while ($context = $contexts->next()) {
            $this->contexts[$context->getId()] = $context;
        }
    }

    /**
     * Show the citations per status, the pending submissions and the job queue.
     */
    protected function status(): void
    {
        $rows = $this->citationQuery(array_keys($this->contexts))
            ->select('s.context_id', 'cs.setting_value')
            ->selectRaw('COUNT(*) AS citations, COUNT(DISTINCT c.publication_id) AS publications')
            ->groupBy('s.context_id', 'cs.setting_value')
            ->get()
            ->groupBy('context_id');

        $pending = [null, CitationProcessingStatus::NOT_PROCESSED, ...self::IN_FLIGHT];
        $inFlightTotal = 0;
        foreach ($this->contexts as $contextId => $context) {
            $contextRows = $rows->get($contextId, collect());
            $lookupEnabled = (bool) $context->getData('citationsMetadataLookup');
            $lookup = $lookupEnabled ? 'on' : 'OFF, skipped';
            echo "Context {$contextId} ({$context->getPath()}), lookup {$lookup}:\n";
            if ($contextRows->isEmpty()) {
                echo "\tno citations\n";
                continue;
            }
            $inFlight = 0;
            foreach ($contextRows->sortBy(fn ($row) => (int) $row->setting_value) as $row) {
                $status = $this->statusName($row->setting_value);
                printf("\t%-14s %8d citations in %6d publications\n", $status, $row->citations, $row->publications);
                if (in_array($status, array_map(fn ($s) => $s->name, self::IN_FLIGHT))) {
                    $inFlight += $row->citations;
                }
            }
            // The repair skips contexts with the lookup disabled, so it cannot be advised for them
            if ($lookupEnabled) {
                $inFlightTotal += $inFlight;
            } elseif ($inFlight > 0) {
                echo "\tNote: {$inFlight} citations are queued or running while the lookup is disabled. If they are stranded,\n"
                    . "\tenable the lookup before repairing them: 'repair' skips contexts with the lookup disabled.\n";
            }
            $submissions = $this->citationQuery([$contextId])->distinct()->count('p.submission_id');
            $pendingSubmissions = $this->whereStatus($this->citationQuery([$contextId]), $pending)->distinct()->count('p.submission_id');
            echo "\tSubmissions with lookups still pending: {$pendingSubmissions} of {$submissions}\n";
        }

        $jobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        echo "\nJob queue: {$jobs} jobs, {$failedJobs} failed jobs (all job types).\n";
        if ($inFlightTotal > 0 && $jobs === 0) {
            echo "Warning: {$inFlightTotal} citations are queued or running, but the job queue is empty. They look stranded;\n"
                . "if no job processing is running right now, queue them again with 'repair --status=queued'.\n";
        }
    }

    /**
     * Queue the lookup of all citations not looked up yet.
     */
    protected function queue(): void
    {
        $limit = $this->options['limit'] ?? null;
        if ($limit !== null && (!ctype_digit((string) $limit) || (int) $limit < 1)) {
            $this->exitWithError('--limit must be a positive number.');
        }
        $this->queueCitations(self::NOT_LOOKED_UP, $limit === null ? null : (int) $limit);
    }

    /**
     * Queue the lookup again for citations in the given status.
     */
    protected function repair(): void
    {
        $statusName = $this->options['status'] ?? null;
        if (!isset(self::REPAIRABLE[$statusName])) {
            $this->exitWithError('--status must be one of: ' . implode(', ', array_keys(self::REPAIRABLE)) . '.');
        }
        if ($statusName === 'queued' && !isset($this->options['force'])) {
            $jobs = DB::table('jobs')->count();
            if ($jobs > 0) {
                $this->exitWithError("The job queue holds {$jobs} jobs, so the queued citations may not be stranded. "
                    . 'Run this once the queue is empty, or give --force if you are sure.');
            }
        }
        $this->queueCitations(self::REPAIRABLE[$statusName], null);
    }

    /**
     * Extract the PIDs from the raw text of the citations not looked up yet, without queuing the lookup.
     */
    protected function extract(): void
    {
        $publications = $this->whereStatus($this->citationQuery(array_keys($this->contexts)), self::NOT_LOOKED_UP)
            ->select('c.publication_id', 's.context_id')
            ->distinct()
            ->orderBy('c.publication_id')
            ->get();

        $extractPids = new ExtractPidsHelper();
        $counts = [];
        foreach ($publications as $row) {
            $citations = Repo::citation()->getByPublicationIds([$row->publication_id])
                ->filter(fn (Citation $citation) => $this->hasStatus($citation, self::NOT_LOOKED_UP));
            foreach ($citations as $citation) {
                $before = array_combine(self::PIDS, array_map(fn (string $pid) => $citation->getData($pid), self::PIDS));
                $extractPids->execute($citation);
                $changed = false;
                foreach ($before as $pid => $value) {
                    // Only fill empty identifiers: one already set may have been corrected by hand
                    if (!empty($value)) {
                        $citation->setData($pid, $value);
                    } elseif (!empty($citation->getData($pid))) {
                        $changed = true;
                    }
                }
                if ($changed) {
                    Repo::citation()->edit($citation, []);
                }
                $count = &$counts[$row->context_id];
                $count['citations'] = ($count['citations'] ?? 0) + 1;
                $count['changed'] = ($count['changed'] ?? 0) + (int) $changed;
                $count['doi'] = ($count['doi'] ?? 0) + (int) !empty($citation->getData('doi'));
                unset($count);
            }
        }

        foreach ($counts as $contextId => $count) {
            echo "Context {$contextId} ({$this->contexts[$contextId]->getPath()}): {$count['citations']} citations checked, "
                . "{$count['changed']} with new PIDs, {$count['doi']} with a DOI.\n";
        }
        if (empty($counts)) {
            echo "No citations not looked up yet.\n";
        }
    }

    /**
     * Queue the lookup of the citations in the given statuses, whole publications at a time,
     * all versions of a submission together, in submission ID order.
     *
     * @param array<?CitationProcessingStatus> $statuses
     * @param ?int $limit Maximum publications with lookups queued or running
     */
    protected function queueCitations(array $statuses, ?int $limit): void
    {
        $enabledContextIds = [];
        foreach ($this->contexts as $contextId => $context) {
            if ($context->getData('citationsMetadataLookup')) {
                $enabledContextIds[] = $contextId;
                continue;
            }
            $skipped = $this->whereStatus($this->citationQuery([$contextId]), $statuses)->count();
            if ($skipped) {
                echo "Skipping {$skipped} citations of context {$contextId} ({$context->getPath()}): the citation metadata lookup is disabled.\n";
            }
        }

        $available = null;
        if ($limit !== null) {
            $inFlight = $this->whereStatus($this->citationQuery($enabledContextIds), self::IN_FLIGHT)
                ->distinct()
                ->count('c.publication_id');
            $available = max(0, $limit - $inFlight);
            echo "{$inFlight} publications have lookups queued or running; queuing at most {$available} more.\n";
        }

        $publications = $this->whereStatus($this->citationQuery($enabledContextIds), $statuses)
            ->select('c.publication_id', 's.context_id', 'p.submission_id')
            ->distinct()
            ->orderBy('p.submission_id')
            ->orderBy('c.publication_id')
            ->when($available !== null, fn (Builder $q) => $q->limit($available))
            ->get();

        $counts = [];
        foreach ($publications as $row) {
            $context = $this->contexts[$row->context_id];
            $citations = Repo::citation()->getByPublicationIds([$row->publication_id])
                ->filter(fn (Citation $citation) => $this->hasStatus($citation, $statuses))
                ->collect();
            foreach ($citations as $citation) {
                Repo::citation()->reprocessCitation($citation, $context);
            }
            $counts[$row->context_id]['publications'] = ($counts[$row->context_id]['publications'] ?? 0) + 1;
            $counts[$row->context_id]['citations'] = ($counts[$row->context_id]['citations'] ?? 0) + $citations->count();
        }

        foreach ($counts as $contextId => $count) {
            echo "Context {$contextId} ({$this->contexts[$contextId]->getPath()}): queued the lookup of {$count['citations']} citations in {$count['publications']} publications.\n";
        }
        if (empty($counts)) {
            echo "Nothing to queue.\n";
        }

        // Citations just queued are QUEUED again, so they would count as left when repairing QUEUED ones
        $remaining = in_array(CitationProcessingStatus::QUEUED, $statuses, true)
            ? 0
            : $this->whereStatus($this->citationQuery($enabledContextIds), $statuses)->count();
        if ($remaining) {
            echo "{$remaining} citations in the selected status are left for a later run.\n";
        }
    }

    /**
     * Get a query on the citations of the given contexts, joined with their status,
     * limited to the submissions in scope.
     */
    protected function citationQuery(array $contextIds): Builder
    {
        return DB::table('citations AS c')
            ->join('publications AS p', 'p.publication_id', '=', 'c.publication_id')
            ->join('submissions AS s', 's.submission_id', '=', 'p.submission_id')
            ->leftJoin(
                'citation_settings AS cs',
                fn ($join) => $join->on('cs.citation_id', '=', 'c.citation_id')
                    ->where('cs.setting_name', '=', 'processingStatus')
                    ->where('cs.locale', '=', '')
            )
            ->whereIn('s.context_id', $contextIds)
            ->when(!empty($this->submissionIds), fn (Builder $q) => $q->whereIn('p.submission_id', $this->submissionIds));
    }

    /**
     * Limit a citation query to the given statuses; null stands for no status.
     *
     * @param array<?CitationProcessingStatus> $statuses
     */
    protected function whereStatus(Builder $query, array $statuses): Builder
    {
        $values = array_map(fn (CitationProcessingStatus $status) => (string) $status->value, array_filter($statuses));
        $withNull = in_array(null, $statuses, true);
        return $query->where(
            fn (Builder $q) => $q->whereIn('cs.setting_value', $values)
                ->when($withNull, fn (Builder $q) => $q->orWhereNull('cs.setting_value'))
        );
    }

    /**
     * Whether the citation is in one of the given statuses; null stands for no status.
     *
     * @param array<?CitationProcessingStatus> $statuses
     */
    protected function hasStatus(Citation $citation, array $statuses): bool
    {
        // getData(), as citations from before 3.6 have no status and getProcessingStatus() requires one
        $value = $citation->getData('processingStatus');
        $status = $value === null ? null : CitationProcessingStatus::tryFrom((int) $value);
        return in_array($status, $statuses, true);
    }

    /**
     * Get the name of a stored status value.
     */
    protected function statusName(?string $value): string
    {
        return $value === null ? 'NO_STATUS' : (CitationProcessingStatus::tryFrom((int) $value)?->name ?? "UNKNOWN ({$value})");
    }

    /**
     * Print an error and exit.
     */
    protected function exitWithError(string $message): never
    {
        echo "Error: {$message}\n";
        exit(1);
    }
}

$tool = new CitationLookupTool($argv ?? []);
$tool->execute();
