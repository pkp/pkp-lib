<?php

/**
 * @file tests/classes/queue/PKPBoundedWorkerBatchesTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Unit tests for ProcessQueueJobs::runBoundedWorkerBatches() (pkp/pkp-lib#9345).
 *
 * Background: in CLI a single process can only load one context's plugins, so the task scheduler drains
 * the queue in bounded batches, each a FRESH `jobs.php work` child (one context per batch). The loop
 * relaunches a fresh child until the per-tick job threshold or the wall-clock budget is reached, or a
 * batch makes no progress. These tests exercise that loop in isolation via three overridable seams
 * (now(), pendingJobCount(), spawnWorkerBatch()) on a ProcessQueueJobs test double, so no database or
 * real subprocess is involved; the end-to-end spawn is and should be covered by the manual verification steps.
 */

namespace PKP\tests\classes\queue;

use PHPUnit\Framework\Attributes\CoversClass;
use PKP\task\ProcessQueueJobs;
use PKP\tests\PKPTestCase;

#[CoversClass(ProcessQueueJobs::class)]
class PKPBoundedWorkerBatchesTest extends PKPTestCase
{
    private const QUEUE = 'test_bounded_batches';

    /** Build the test-double task with a controllable clock, job count, and per-batch progress. */
    private function task(int $pending, array $batchProcessed = [], float $clockStep = 0.0): TestableProcessQueueJobs
    {
        $task = new TestableProcessQueueJobs();
        $task->pending = $pending;
        $task->batchProcessed = $batchProcessed;
        $task->clockStep = $clockStep;

        return $task;
    }

    /** Return the value of a `--name=value` flag from a recorded spawn command, or null. */
    private function flag(array $command, string $name): ?string
    {
        foreach ($command as $arg) {
            if (str_starts_with($arg, $name . '=')) {
                return substr($arg, strlen($name) + 1);
            }
        }

        return null;
    }

    public function testStopsWhenQueueDrains(): void
    {
        $task = $this->task(8, [3, 3, 5]);

        $processed = $task->runBatches(30, 40, self::QUEUE);

        $this->assertSame(8, $processed);
        $this->assertCount(3, $task->spawnCalls);
        $this->assertSame(0, $task->pending);
    }

    public function testStopsAtJobThresholdAndShrinksQuotaPerBatch(): void
    {
        $task = $this->task(100, [10, 10, 10, 10]);

        $processed = $task->runBatches(30, 40, self::QUEUE);

        // Threshold is 30, so it must stop after exactly three 10-job batches (no fourth spawn).
        $this->assertSame(30, $processed);
        $this->assertCount(3, $task->spawnCalls);

        // Each batch's --max-jobs is the REMAINING quota: 30, then 20, then 10.
        $this->assertSame('30', $this->flag($task->spawnCalls[0], '--max-jobs'));
        $this->assertSame('20', $this->flag($task->spawnCalls[1], '--max-jobs'));
        $this->assertSame('10', $this->flag($task->spawnCalls[2], '--max-jobs'));
    }

    public function testStopsAtWallClockBudget(): void
    {
        // Clock advances 2s per now() call; budget 7s leaves room for two batches before the deadline.
        $task = $this->task(100, [1, 1, 1, 1], 2.0);

        $processed = $task->runBatches(30, 7, self::QUEUE);

        $this->assertCount(2, $task->spawnCalls);
        $this->assertSame(2, $processed);
    }

    public function testStopsWhenABatchMakesNoProgress(): void
    {
        // First batch processes nothing (e.g. all jobs reserved by another worker) -> stop, no busy loop.
        $task = $this->task(10, [0]);

        $processed = $task->runBatches(30, 40, self::QUEUE);

        $this->assertSame(0, $processed);
        $this->assertCount(1, $task->spawnCalls);
    }

    public function testStopsWhenASpawnFails(): void
    {
        $task = $this->task(10, [5]);
        $task->failSpawn = true;

        $processed = $task->runBatches(30, 40, self::QUEUE);

        $this->assertSame(0, $processed);
        $this->assertCount(1, $task->spawnCalls);
    }

    public function testDoesNotSpawnWhenQueueIsEmpty(): void
    {
        $task = $this->task(0);

        $processed = $task->runBatches(30, 40, self::QUEUE);

        $this->assertSame(0, $processed);
        $this->assertCount(0, $task->spawnCalls);
    }

    public function testEachBatchCommandIsAFreshBoundedNonRestartingWorker(): void
    {
        $task = $this->task(5, [5]);

        $task->runBatches(30, 40, self::QUEUE);

        $command = $task->spawnCalls[0];
        $this->assertContains('work', $command);
        $this->assertContains('--queue=' . self::QUEUE, $command);
        $this->assertContains('--stop-when-empty', $command);
        $this->assertContains('--no-self-restart', $command);
        $this->assertNotNull($this->flag($command, '--max-time'));
    }

    public function testWallClockBudgetIsClampedBelowTheMinuteTick(): void
    {
        // A budget above the clamp (55s) must be capped; the first batch's --max-time reflects it.
        $task = $this->task(5, [5]);

        $task->runBatches(30, 999, self::QUEUE);

        $this->assertSame('55', $this->flag($task->spawnCalls[0], '--max-time'));
    }
}

/**
 * Test double exposing the batch loop's three seams so it can run with a scripted clock and job count
 * and record the spawned commands, without a database or a real worker subprocess. It also skips
 * ScheduledTask's constructor (which only sets up the execution-log file/dir, unused by the loop) and
 * exposes the protected loop via a public passthrough.
 */
class TestableProcessQueueJobs extends ProcessQueueJobs
{
    /** Current fake clock value, advanced by $clockStep on every now() call. */
    public float $clock = 0.0;
    public float $clockStep = 0.0;

    /** Pending job count returned by pendingJobCount(); decremented as batches "process" jobs. */
    public int $pending = 0;

    /** Number of jobs each successive batch will process. */
    public array $batchProcessed = [];

    /** When true, spawnWorkerBatch() reports a killed/failed batch. */
    public bool $failSpawn = false;

    /** Recorded command arrays, one per spawnWorkerBatch() call. */
    public array $spawnCalls = [];

    private int $batchIndex = 0;

    public function __construct()
    {
        // Intentionally skip ScheduledTask::__construct() — its execution-log file/dir setup is
        // irrelevant to the batch loop, which reads no constructor state.
    }

    /** Public passthrough so tests can drive the protected loop directly. */
    public function runBatches(int $jobThreshold, int $budgetSeconds, ?string $queue = null): int
    {
        return $this->runBoundedWorkerBatches($jobThreshold, $budgetSeconds, $queue);
    }

    protected function now(): float
    {
        $now = $this->clock;
        $this->clock += $this->clockStep;

        return $now;
    }

    protected function pendingJobCount(string $queue): int
    {
        return $this->pending;
    }

    protected function spawnWorkerBatch(array $command, float $hardTimeout): int
    {
        $this->spawnCalls[] = $command;

        if ($this->failSpawn) {
            return self::BATCH_SPAWN_FAILED;
        }

        $this->pending = max(0, $this->pending - ($this->batchProcessed[$this->batchIndex] ?? 0));
        $this->batchIndex++;

        return 0;
    }
}
