<?php

/**
 * @file tests/classes/queue/PKPLoopingContextChangeTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Regression tests for the daemon worker's "quit on context change" decision
 *   (pkp/pkp-lib#9345).
 *
 * Background: on the daemon path, the Looping listener peeks at the next job before popping it and
 * quits the worker (so a fresh process re-bootstraps plugins/hooks/schema) when that job belongs to a
 * DIFFERENT context than the one the worker committed to in Queue::before. The criterion is purely a
 * context change with `null` (site/non-context level) treated as a first-class context value, so
 * null->context, context->null and context->context all relaunch while same-context (incl. null==null)
 * keeps processing. It is NOT gated on multi-context sites. These tests drive
 * PKPQueueProvider::nextJobChangesContext() directly (the Looping listener early-returns under
 * runningUnitTests()), seeding the jobs table to verify both the context comparison and the peek's
 * availability predicate (which must mirror DatabaseQueue's pop: retry_after + available_at).
 */

namespace PKP\tests\classes\queue;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\core\PKPQueueProvider;
use PKP\tests\PKPTestCase;
use ReflectionMethod;
use ReflectionProperty;

#[RunTestsInSeparateProcesses]
#[CoversClass(PKPQueueProvider::class)]
class PKPLoopingContextChangeTest extends PKPTestCase
{
    private const QUEUE = 'test_looping_context_change';
    private const CONNECTION = 'database';

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('jobs')->where('queue', self::QUEUE)->delete();
    }

    protected function tearDown(): void
    {
        DB::table('jobs')->where('queue', self::QUEUE)->delete();
        parent::tearDown();
    }

    /** Mark the provider as committed to the given context (null = site/non-context level). */
    private function commit(PKPQueueProvider $provider, ?int $contextId): void
    {
        $committed = new ReflectionProperty($provider, 'committedContextId');
        $committed->setAccessible(true);
        $committed->setValue($provider, $contextId);

        $flag = new ReflectionProperty($provider, 'contextCommitted');
        $flag->setAccessible(true);
        $flag->setValue($provider, true);
    }

    /** Invoke the protected decision method. */
    private function decide(PKPQueueProvider $provider): bool
    {
        $method = new ReflectionMethod($provider, 'nextJobChangesContext');
        $method->setAccessible(true);
        return $method->invoke($provider, self::CONNECTION, self::QUEUE);
    }

    /**
     * Insert an available job on the test queue. A null $contextId omits the context_id key entirely,
     * exactly as Queue::createPayloadUsing does for non-context-aware jobs.
     */
    private function insertJob(?int $contextId, ?int $reservedAt = null, ?int $availableAtOffset = -60): void
    {
        $payload = ['displayName' => $contextId === null ? 'NullJob' : 'CtxJob'];
        if ($contextId !== null) {
            $payload['context_id'] = $contextId;
        }

        DB::table('jobs')->insert([
            'queue' => self::QUEUE,
            'payload' => json_encode($payload),
            'attempts' => 0,
            'reserved_at' => $reservedAt,
            'available_at' => now()->getTimestamp() + $availableAtOffset,
            'created_at' => now()->getTimestamp() - 60,
        ]);
    }

    public function testNotCommittedNeverSignalsChange(): void
    {
        $provider = app('pkpJobQueue');
        $this->insertJob(5); // a context-aware job is waiting...
        // ...but the worker has not committed to any context yet → nothing to compare → keep looping.
        $this->assertFalse($this->decide($provider));
    }

    public function testNullCommittedWithNullNextKeepsProcessing(): void
    {
        $provider = app('pkpJobQueue');
        $this->commit($provider, null);
        $this->insertJob(null);
        $this->assertFalse($this->decide($provider), 'null == null is the same context');
    }

    public function testNullCommittedWithContextNextRelaunches(): void
    {
        $provider = app('pkpJobQueue');
        $this->commit($provider, null);
        $this->insertJob(5);
        $this->assertTrue($this->decide($provider), 'site/null worker must relaunch before a context job');
    }

    public function testSameContextKeepsProcessing(): void
    {
        $provider = app('pkpJobQueue');
        $this->commit($provider, 5);
        $this->insertJob(5);
        $this->assertFalse($this->decide($provider));
    }

    public function testDifferentContextRelaunches(): void
    {
        $provider = app('pkpJobQueue');
        $this->commit($provider, 5);
        $this->insertJob(7);
        $this->assertTrue($this->decide($provider));
    }

    public function testContextToNullRelaunches(): void
    {
        $provider = app('pkpJobQueue');
        $this->commit($provider, 5);
        $this->insertJob(null);
        $this->assertTrue($this->decide($provider), 'symmetric: context -> null is also a change');
    }

    public function testNoNextJobKeepsProcessing(): void
    {
        $provider = app('pkpJobQueue');
        $this->commit($provider, 5);
        // No jobs on the queue.
        $this->assertFalse($this->decide($provider));
    }

    public function testEarliestJobByIdIsPeeked(): void
    {
        $provider = app('pkpJobQueue');
        $this->commit($provider, 5);
        $this->insertJob(5); // earlier id, same context
        $this->insertJob(7); // later id, different context
        $this->assertFalse($this->decide($provider), 'the next job to pop (lowest id) is the same context');
    }

    public function testRecentlyReservedNextJobIsNotPeeked(): void
    {
        $provider = app('pkpJobQueue');
        $this->commit($provider, 5);
        // A different-context job reserved just now: the worker will NOT pop it (within retry_after),
        // so the peek must not see it either → no context change.
        $this->insertJob(7, reservedAt: now()->getTimestamp());
        $this->assertFalse(
            $this->decide($provider),
            'a recently reserved job is not available to pop, so it must not trigger a relaunch'
        );
    }

    public function testNotYetAvailableNextJobIsNotPeeked(): void
    {
        $provider = app('pkpJobQueue');
        $this->commit($provider, 5);
        // A different-context job scheduled for the future: not yet available → must not be peeked.
        $this->insertJob(7, availableAtOffset: 3600);
        $this->assertFalse(
            $this->decide($provider),
            'a delayed (available_at in the future) job must not trigger a relaunch'
        );
    }
}
