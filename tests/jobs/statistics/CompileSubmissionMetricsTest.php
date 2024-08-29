<?php

/**
 * @file tests/jobs/statistics/CompileSubmissionMetricsTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for compiling submission metrics job.
 */

namespace PKP\tests\jobs\statistics;

use Mockery;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;
use PKP\jobs\statistics\CompileSubmissionMetrics;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\CoversClass;

#[RunTestsInSeparateProcesses]
#[CoversClass(CompileSubmissionMetrics::class)]
class CompileSubmissionMetricsTest extends PKPTestCase
{
    /**
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:44:"PKP\\jobs\\statistics\\CompileSubmissionMetrics":3:{s:9:"\0*\0loadId";s:25:"usage_events_20240130.log";s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
    END;

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            CompileSubmissionMetrics::class,
            unserialize($this->serializedJobData)
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        /** @var CompileSubmissionMetrics $compileSubmissionMetricsJob */
        $compileSubmissionMetricsJob = unserialize($this->serializedJobData);

        $temporaryTotalsDAOMock = Mockery::mock(\APP\statistics\TemporaryTotalsDAO::class)
            ->makePartial()
            ->shouldReceive([
                'removeDoubleClicks' => null,
            ])
            ->withAnyArgs()
            ->getMock();

        DAORegistry::registerDAO('TemporaryTotalsDAO', $temporaryTotalsDAOMock);

        $compileSubmissionMetricsJob->handle();

        $this->expectNotToPerformAssertions();
    }
}
