<?php

/**
 * @file tests/classes/submissionFile/SubmissionFileTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileTest
 *
 * @ingroup tests_classes_submissionFile
 *
 * @see SubmissionFile
 * @see https://github.com/pkp/pkp-lib/issues/13816
 *
 * @brief Test class for the SubmissionFile class and for the lookup of a file by its "best" ID
 */

namespace PKP\tests\classes\submissionFile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PKP\services\PKPSchemaService;
use PKP\submissionFile\DAO;
use PKP\submissionFile\SubmissionFile;
use PKP\tests\PKPTestCase;

#[CoversClass(SubmissionFile::class)]
#[CoversClass(DAO::class)]
class SubmissionFileTest extends PKPTestCase
{
    private const SUBMISSION_ID = 7;

    /**
     * Build a file without touching the database.
     */
    private function newFile(int $id, int $fileStage = SubmissionFile::SUBMISSION_FILE_PROOF, ?string $publisherId = null): SubmissionFile
    {
        $file = new SubmissionFile();
        $file->setData('id', $id);
        $file->setData('fileStage', $fileStage);
        if ($publisherId !== null) {
            $file->setStoredPubId('publisher-id', $publisherId);
        }

        return $file;
    }

    /**
     * Build a DAO whose two lookups are replaced, so no database is needed.
     *
     * @param ?SubmissionFile $byId What get() returns
     * @param ?SubmissionFile $byPubId What getByPubId() returns
     */
    private function newDao(?SubmissionFile $byId, ?SubmissionFile $byPubId): MockObject&DAO
    {
        $dao = $this->getMockBuilder(DAO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'getByPubId'])
            ->getMock();
        $dao->method('get')->willReturn($byId);
        $dao->method('getByPubId')->willReturn($byPubId);

        return $dao;
    }

    public function testSchemaHasPublisherId(): void
    {
        $schema = app()->get('schema')->get(PKPSchemaService::SCHEMA_SUBMISSION_FILE);
        $this->assertObjectHasProperty('pub-id::publisher-id', $schema->properties);
        $this->assertSame('string', $schema->properties->{'pub-id::publisher-id'}->type);
    }

    public function testBestIdIsTheFileId(): void
    {
        $this->assertSame(12, $this->newFile(12)->getBestId());
    }

    public function testBestIdIgnoresThePublisherId(): void
    {
        $file = $this->newFile(12, SubmissionFile::SUBMISSION_FILE_PROOF, 'my-publisher-id');
        $this->assertSame('my-publisher-id', $file->getStoredPubId('publisher-id'));
        $this->assertSame(12, $file->getBestId());
    }

    public function testGetByBestIdFindsAFileByItsId(): void
    {
        $file = $this->newFile(12);
        $dao = $this->newDao($file, null);
        $dao->expects($this->never())->method('getByPubId');
        $this->assertSame($file, $dao->getByBestId('12', self::SUBMISSION_ID));
        $this->assertSame($file, $dao->getByBestId(12, self::SUBMISSION_ID));
    }

    public function testGetByBestIdFallsBackToThePublisherIdForOldUrls(): void
    {
        $file = $this->newFile(12, SubmissionFile::SUBMISSION_FILE_PROOF, 'my-publisher-id');
        $dao = $this->newDao(null, $file);
        $dao->expects($this->once())
            ->method('getByPubId')
            ->with('publisher-id', 'my-publisher-id', self::SUBMISSION_ID, null);
        $this->assertSame($file, $dao->getByBestId('my-publisher-id', self::SUBMISSION_ID));
    }

    public function testGetByBestIdUsesTheFileIdBeforeThePublisherId(): void
    {
        $file = $this->newFile(12);
        $dao = $this->newDao($file, $this->newFile(99, SubmissionFile::SUBMISSION_FILE_PROOF, '12'));
        $dao->expects($this->never())->method('getByPubId');
        $this->assertSame($file, $dao->getByBestId('12', self::SUBMISSION_ID));
    }

    public function testGetByBestIdFallsBackToThePublisherIdForAnUnknownNumericId(): void
    {
        $file = $this->newFile(99, SubmissionFile::SUBMISSION_FILE_PROOF, '12');
        $dao = $this->newDao(null, $file);
        $this->assertSame($file, $dao->getByBestId('12', self::SUBMISSION_ID));
    }

    public function testGetByBestIdReturnsNullForUnknownOrEmptyIds(): void
    {
        $dao = $this->newDao(null, null);
        $this->assertNull($dao->getByBestId('does-not-exist', self::SUBMISSION_ID));
        $this->assertNull($dao->getByBestId('', self::SUBMISSION_ID));
        $this->assertNull($dao->getByBestId('12', self::SUBMISSION_ID));
    }

    public function testGetByBestIdOnlyReturnsFilesThatMayBeServed(): void
    {
        $this->assertNull($this->newDao($this->newFile(12, SubmissionFile::SUBMISSION_FILE_SUBMISSION), null)->getByBestId('12', self::SUBMISSION_ID));
        foreach ([SubmissionFile::SUBMISSION_FILE_PROOF, SubmissionFile::SUBMISSION_FILE_DEPENDENT, SubmissionFile::SUBMISSION_FILE_MEDIA] as $fileStage) {
            $file = $this->newFile(12, $fileStage);
            $this->assertSame($file, $this->newDao($file, null)->getByBestId('12', self::SUBMISSION_ID));
        }
    }
}
