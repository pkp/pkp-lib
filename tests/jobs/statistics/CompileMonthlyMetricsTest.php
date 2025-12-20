<?php

/**
 * @file tests/jobs/statistics/CompileMonthlyMetricsTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for compiling monthly metrics job.
 */

namespace PKP\tests\jobs\statistics;

use Mockery;
use PKP\tests\PKPTestCase;
use PKP\jobs\statistics\CompileMonthlyMetrics;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\CoversClass;

#[RunTestsInSeparateProcesses]
#[CoversClass(CompileMonthlyMetrics::class)]
class CompileMonthlyMetricsTest extends PKPTestCase
{
    /**
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:41:"PKP\\jobs\\statistics\\CompileMonthlyMetrics":4:{s:8:"\0*\0month";s:6:"202404";s:7:"\0*\0site";O:13:"PKP\site\Site":6:{s:5:"_data";a:16:{s:8:"redirect";i:0;s:13:"primaryLocale";s:2:"en";s:17:"minPasswordLength";i:6;s:16:"installedLocales";a:2:{i:0;s:2:"en";i:1;s:5:"fr_CA";}s:16:"supportedLocales";a:2:{i:0;s:2:"en";i:1;s:5:"fr_CA";}s:17:"compressStatsLogs";b:0;s:12:"contactEmail";a:1:{s:2:"en";s:23:"pkpadmin@mailinator.com";}s:11:"contactName";a:2:{s:2:"en";s:20:"Open Journal Systems";s:5:"fr_CA";s:20:"Open Journal Systems";}s:16:"enableBulkEmails";a:2:{i:0;i:1;i:1;i:2;}s:19:"enableGeoUsageStats";s:8:"disabled";s:27:"enableInstitutionUsageStats";b:0;s:19:"isSiteSushiPlatform";b:0;s:16:"isSushiApiPublic";b:1;s:19:"keepDailyUsageStats";b:0;s:15:"themePluginPath";s:7:"default";s:12:"uniqueSiteId";s:36:"A5717D41-959C-4D94-83DC-EB4F10BBE5AF";}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
    END;

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            CompileMonthlyMetrics::class,
            unserialize($this->serializedJobData)
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        /** @var CompileMonthlyMetrics $compileMonthlyMetricsJob */
        $compileMonthlyMetricsJob = unserialize($this->serializedJobData);

        $statsGeoServiceMock = Mockery::mock(\PKP\services\PKPStatsGeoService::class)
            ->makePartial()
            ->shouldReceive([
                'deleteMonthlyMetrics' => null,
                'addMonthlyMetrics' => null,
                'deleteDailyMetrics' => null,
            ])
            ->withAnyArgs()
            ->getMock();

        app()->instance('geoStats', $statsGeoServiceMock);

        $statsSushiService = Mockery::mock(\PKP\services\PKPStatsSushiService::class)
            ->makePartial()
            ->shouldReceive([
                'deleteMonthlyMetrics' => null,
                'addMonthlyMetrics' => null,
                'deleteDailyMetrics' => null,
            ])
            ->withAnyArgs()
            ->getMock();
        
        app()->instance('sushiStats', $statsSushiService);

        $compileMonthlyMetricsJob->handle();

        $this->expectNotToPerformAssertions();
    }
}
