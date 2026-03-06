<?php

/**
 * @file tests/jobs/doi/DepositContextTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for context deposit job.
 */

namespace PKP\tests\jobs\doi;

use Mockery;
use PKP\db\DAORegistry;
use PKP\context\Context;
use APP\core\Application;
use PKP\tests\PKPTestCase;
use PKP\jobs\doi\DepositContext;
use APP\doi\Repository as DoiRepository;

/**
 * @runTestsInSeparateProcesses
 *
 * @see https://docs.phpunit.de/en/9.6/annotations.html#runtestsinseparateprocesses
 */
class DepositContextTest extends PKPTestCase
{
    /**
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:27:"PKP\\jobs\\doi\\DepositContext":3:{s:12:"\0*\0contextId";i:1;s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
    END;

    /**
     * @see PKPTestCase::getMockedDAOs()
     */
    protected function getMockedDAOs(): array
    {
        return array_filter([
            ...parent::getMockedDAOs(),
            substr(strrchr(get_class(Application::getContextDAO()), '\\'), 1),
        ]);
    }

    /**
     * @see PKPTestCase::getMockedContainerKeys()
     */
    protected function getMockedContainerKeys(): array
    {
        return [
            ...parent::getMockedContainerKeys(),
            DoiRepository::class,
        ];
    }

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            DepositContext::class,
            unserialize($this->serializedJobData)
        );
    }

    /**
     * Test job is a proper context aware job instance and getContextId returns expected value
     */
    public function testUnserializationGetProperContextId(): void
    {
        $job = unserialize($this->serializedJobData);
        $this->assertInstanceOf(\PKP\queue\ContextAwareJob::class, $job);
        $this->assertIsInt($job->getContextId());
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        /** @var DepositContext $depositContextJob */
        $depositContextJob = unserialize($this->serializedJobData);

        $contextDaoClass = get_class(Application::getContextDAO());

        /**
         * @disregard P1013 PHP Intelephense error suppression
         * @see https://github.com/bmewburn/vscode-intelephense/issues/568
         */
        $contextMock = Mockery::mock(get_class(Application::getContextDAO()->newDataObject()))
            ->makePartial()
            ->shouldReceive('getData')
            ->with(Context::SETTING_DOI_AUTOMATIC_DEPOSIT)
            ->andReturn(true)
            ->shouldReceive('getLocalizedData')
            ->withAnyArgs()
            ->andReturn('')
            ->getMock();

        $contextDaoMock = Mockery::mock($contextDaoClass)
            ->makePartial()
            ->shouldReceive('getById')
            ->withAnyArgs()
            ->andReturn($contextMock)
            ->getMock();

        DAORegistry::registerDAO(substr(strrchr($contextDaoClass, '\\'), 1), $contextDaoMock);

        $doiRepoMock = Mockery::mock(app(DoiRepository::class))
            ->makePartial()
            ->shouldReceive('depositAll')
            ->with($contextMock)
            ->andReturn(null)
            ->getMock();

        app()->instance(DoiRepository::class, $doiRepoMock);

        $this->assertNull($depositContextJob->handle());
    }
}
