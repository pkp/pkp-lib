<?php

/**
 * @file tests/jobs/statistics/ArchiveUsageStatsLogFileTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for archiving usage stats log file job.
 */

namespace PKP\tests\jobs\statistics;

use ReflectionClass;
use PKP\task\FileLoader;
use PKP\file\FileManager;
use PKP\tests\PKPTestCase;
use APP\statistics\StatisticsHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\jobs\statistics\ArchiveUsageStatsLogFile;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
#[CoversClass(ArchiveUsageStatsLogFile::class)]
class ArchiveUsageStatsLogFileTest extends PKPTestCase
{
    /**
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:44:"PKP\\jobs\\statistics\\ArchiveUsageStatsLogFile":4:{s:9:"\0*\0loadId";s:25:"usage_events_20240130.log";s:7:"\0*\0site";O:13:"PKP\site\Site":6:{s:5:"_data";a:16:{s:8:"redirect";i:0;s:13:"primaryLocale";s:2:"en";s:17:"minPasswordLength";i:6;s:16:"installedLocales";a:2:{i:0;s:2:"en";i:1;s:5:"fr_CA";}s:16:"supportedLocales";a:2:{i:0;s:2:"en";i:1;s:5:"fr_CA";}s:17:"compressStatsLogs";b:0;s:12:"contactEmail";a:1:{s:2:"en";s:23:"pkpadmin@mailinator.com";}s:11:"contactName";a:2:{s:2:"en";s:20:"Open Journal Systems";s:5:"fr_CA";s:20:"Open Journal Systems";}s:16:"enableBulkEmails";a:2:{i:0;i:1;i:1;i:2;}s:19:"enableGeoUsageStats";s:8:"disabled";s:27:"enableInstitutionUsageStats";b:0;s:19:"isSiteSushiPlatform";b:0;s:16:"isSushiApiPublic";b:1;s:19:"keepDailyUsageStats";b:0;s:15:"themePluginPath";s:7:"default";s:12:"uniqueSiteId";s:36:"A5717D41-959C-4D94-83DC-EB4F10BBE5AF";}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
    END;

    /**
     * Content example from OJS 3.4.0
     */
    protected $dummyFileContent = '{"time":"2023-08-07 17:27:11","ip":"228dc4e5b6424e9dad52f21261cb2ab5f4651d9cb426d6fdb3d71d5ab8e2ae83","userAgent":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko\/20100101 Firefox\/115.0","canonicalUrl":"http:\/\/ojs-stable-3_4_0.test\/index.php\/publicknowledge\/index","assocType":256,"contextId":1,"submissionId":null,"representationId":null,"submissionFileId":null,"fileType":null,"country":null,"region":null,"city":null,"institutionIds":[],"version":"3.4.0.0","issueId":null,"issueGalleyId":null}';

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            ArchiveUsageStatsLogFile::class,
            unserialize($this->serializedJobData)
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        /** @var ArchiveUsageStatsLogFile $archiveUsageStatsLogFileJob */
        $archiveUsageStatsLogFileJob = unserialize($this->serializedJobData);

        // we need to create a dummy file if not existed as to avoid mocking PHP's built in functions
        $dummyFileName = $this->createDummyFileIfNeeded($archiveUsageStatsLogFileJob, 'loadId');

        $this->createArchiveDirectoryIfRequired();

        $archiveUsageStatsLogFileJob->handle();

        if ($dummyFileName) {
            unlink(
                StatisticsHelper::getUsageStatsDirPath()
                    . '/'
                    . FileLoader::FILE_LOADER_PATH_ARCHIVE
                    . '/'
                    .$dummyFileName
            );
        }

        $this->expectNotToPerformAssertions();
    }

    /**
     * Create the dummy file with dummy content if required
     */
    protected function createDummyFileIfNeeded(ArchiveUsageStatsLogFile $job, string $propertyName): ?string
    {
        $reflection = new ReflectionClass($job);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $fileName = $property->getValue($job);

        $filePath = StatisticsHelper::getUsageStatsDirPath()
            . DIRECTORY_SEPARATOR
            . FileLoader::FILE_LOADER_PATH_DISPATCH
            . DIRECTORY_SEPARATOR;

        if (!file_exists($filePath . $fileName)) {

            // create the 'FileLoader::FILE_LOADER_PATH_DISPATCH' directory if not exists
            if (!file_exists($filePath)) {
                $fileManager = new FileManager();
                $fileManager->mkdirtree($filePath);
            }

            touch($filePath . $fileName);
            
            file_put_contents($filePath . $fileName, $this->dummyFileContent);
            return $fileName;
        }

        return null;
    }

    /**
     * Create the archive path/directory as needed
     */
    protected function createArchiveDirectoryIfRequired(): bool
    {
        $filePath = StatisticsHelper::getUsageStatsDirPath()
            . DIRECTORY_SEPARATOR
            . FileLoader::FILE_LOADER_PATH_ARCHIVE
            . DIRECTORY_SEPARATOR;
        
        if (file_exists($filePath)) {
            return true;
        }

        // create the 'FileLoader::FILE_LOADER_PATH_ARCHIVE' directory if not exists
        $fileManager = new FileManager();
        $fileManager->mkdirtree($filePath);

        return file_exists($filePath);
    }
}
