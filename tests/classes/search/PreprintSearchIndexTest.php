<?php

/**
 * @file tests/classes/search/PreprintSearchIndexTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintSearchIndexTest
 * @ingroup tests_classes_search
 *
 * @see PreprintSearchIndex
 *
 * @brief Test class for the PreprintSearchIndex class
 */

import('lib.pkp.tests.PKPTestCase');
import('classes.submission.Submission');

use Illuminate\Support\Facades\App;
use PKP\core\ArrayItemIterator;
use PKP\db\DAORegistry;
use PKP\galley\DAO as GalleyDAO;

class PreprintSearchIndexTest extends PKPTestCase
{
    //
    // Implementing protected template methods from PKPTestCase
    //
    /**
     * @see PKPTestCase::getMockedDAOs()
     */
    protected function getMockedDAOs()
    {
        $mockedDaos = parent::getMockedDAOs();
        $mockedDaos += [
            'PreprintSearchDAO', 'ServerDAO',
        ];
        return $mockedDaos;
    }

    /**
     * @see PKPTestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();
        HookRegistry::rememberCalledHooks();
    }

    /**
     * @see PKPTestCase::tearDown()
     */
    protected function tearDown(): void
    {
        HookRegistry::resetCalledHooks();
        parent::tearDown();
    }


    //
    // Unit tests
    //
    /**
     * @covers PreprintSearchIndex
     */
    public function testUpdateFileIndexViaPluginHook()
    {
        // Diverting to the search plugin hook.
        HookRegistry::register('PreprintSearchIndex::submissionFileChanged', [$this, 'callbackUpdateFileIndex']);

        // Simulate updating an preprint file via hook.
        import('lib.pkp.classes.submissionFile.SubmissionFile');
        $submissionFile = new SubmissionFile();
        $submissionFile->setId(2);
        $preprintSearchIndex = Application::getSubmissionSearchIndex();
        $preprintSearchIndex->submissionFileChanged(0, 1, $submissionFile);

        // Test whether the hook was called.
        $calledHooks = HookRegistry::getCalledHooks();
        $lastHook = array_pop($calledHooks);
        self::assertEquals('PreprintSearchIndex::submissionFileChanged', $lastHook[0]);

        // Remove the test hook.
        HookRegistry::clear('PreprintSearchIndex::submissionFileChanged');
    }

    /**
     * @covers PreprintSearchIndex
     */
    public function testDeleteTextIndex()
    {
        // Prepare the mock environment for this test.
        $this->registerMockPreprintSearchDAO($this->never(), $this->atLeastOnce());

        // Make sure that no hook is being called.
        HookRegistry::clear('PreprintSearchIndex::submissionFileDeleted');

        // Test deleting an preprint from the index with a mock database back-end.#
        $preprintSearchIndex = Application::getSubmissionSearchIndex();
        $preprintSearchIndex->submissionFileDeleted(0);
    }

    /**
     * @covers PreprintSearchIndex
     */
    public function testDeleteTextIndexViaPluginHook()
    {
        // Diverting to the search plugin hook.
        HookRegistry::register('PreprintSearchIndex::submissionFileDeleted', [$this, 'callbackDeleteTextIndex']);

        // The search DAO should not be called.
        $this->registerMockPreprintSearchDAO($this->never(), $this->never());

        // Simulate deleting preprint index via hook.
        $preprintSearchIndex = Application::getSubmissionSearchIndex();
        $preprintSearchIndex->submissionFileDeleted(0, 1, 2);

        // Test whether the hook was called.
        $calledHooks = HookRegistry::getCalledHooks();
        $lastHook = array_pop($calledHooks);
        self::assertEquals('PreprintSearchIndex::submissionFileDeleted', $lastHook[0]);

        // Remove the test hook.
        HookRegistry::clear('PreprintSearchIndex::submissionFileDeleted');
    }

    /**
     * @covers PreprintSearchIndex
     */
    public function testRebuildIndex()
    {
        // Prepare the mock environment for this test.
        $this->registerMockPreprintSearchDAO($this->atLeastOnce(), $this->never());
        $this->registerMockServerDAO();

        // Make sure that no hook is being called.
        HookRegistry::clear('PreprintSearchIndex::rebuildIndex');

        // Test log output.
        $this->expectOutputString("##search.cli.rebuildIndex.clearingIndex## ... ##search.cli.rebuildIndex.done##\n");

        // Test rebuilding the index with a mock database back-end.
        $preprintSearchIndex = Application::getSubmissionSearchIndex();
        $preprintSearchIndex->rebuildIndex(true);
    }

    /**
     * @covers PreprintSearchIndex
     */
    public function testRebuildIndexViaPluginHook()
    {
        // Diverting to the search plugin hook.
        HookRegistry::register('PreprintSearchIndex::rebuildIndex', [$this, 'callbackRebuildIndex']);

        // Test log output.
        $this->expectOutputString('Some log message from the plug-in.');

        // Simulate rebuilding the index via hook.
        $preprintSearchIndex = Application::getSubmissionSearchIndex();
        $preprintSearchIndex->rebuildIndex(true); // With log
        $preprintSearchIndex->rebuildIndex(false); // Without log (that's why we expect the log message to appear only once).

        // Remove the test hook.
        HookRegistry::clear('PreprintSearchIndex::rebuildIndex');
    }

    /**
     * @covers PreprintSearchIndex
     */
    public function testIndexPreprintMetadata()
    {
        $this->markTestSkipped(); // Temporarily disabled!

        // Make sure that no hook is being called.
        HookRegistry::clear('PreprintSearchIndex::preprintMetadataChanged');

        // FIXME: getAuthors function removed
        // Mock an preprint so that the authors are not
        // being retrieved from the database.
        $preprint = $this->getMockBuilder(Preprint::class)
            ->setMethods(['getAuthors'])
            ->getMock();
        $preprint->expects($this->any())
            ->method('getAuthors')
            ->will($this->returnValue([]));

        // Test indexing an preprint with a mock environment.
        $preprintSearchIndex = $this->getMockPreprintSearchIndex($this->atLeastOnce());
        $preprintSearchIndex->submissionMetadataChanged($preprint);
    }

    /**
     * @covers PreprintSearchIndex
     */
    public function testIndexPreprintMetadataViaPluginHook()
    {
        // Diverting to the search plugin hook.
        HookRegistry::register('PreprintSearchIndex::preprintMetadataChanged', [$this, 'callbackIndexPreprintMetadata']);

        // Simulate indexing via hook.
        $preprint = new Submission();
        $preprintSearchIndex = $this->getMockPreprintSearchIndex($this->never());
        $preprintSearchIndex->submissionMetadataChanged($preprint);

        // Test whether the hook was called.
        $calledHooks = HookRegistry::getCalledHooks();
        self::assertEquals('PreprintSearchIndex::preprintMetadataChanged', $calledHooks[0][0]);

        // Remove the test hook.
        HookRegistry::clear('PreprintSearchIndex::preprintMetadataChanged');
    }

    /**
     * @covers PreprintSearchIndex
     */
    public function testIndexSubmissionFiles()
    {
        $this->markTestSkipped(); // Temporarily disabled!

        // Make sure that no hook is being called.
        HookRegistry::clear('PreprintSearchIndex::submissionFilesChanged');
        $this->registerFileDAOs(true);

        // Test indexing an preprint with a mock environment.
        $preprint = new Submission();
        $preprintSearchIndex = Application::getSubmissionSearchIndex();
        $preprintSearchIndex->submissionFilesChanged($preprint);
    }

    /**
     * @covers PreprintSearchIndex
     */
    public function testIndexSubmissionFilesViaPluginHook()
    {
        // Diverting to the search plugin hook.
        HookRegistry::register('PreprintSearchIndex::submissionFilesChanged', [$this, 'callbackIndexSubmissionFiles']);

        // The file DAOs should not be called.
        $this->registerFileDAOs(false);

        // Simulate indexing via hook.
        $preprint = new Submission();
        $preprintSearchIndex = Application::getSubmissionSearchIndex();
        $preprintSearchIndex->submissionFilesChanged($preprint);

        // Test whether the hook was called.
        $calledHooks = HookRegistry::getCalledHooks();
        $lastHook = array_pop($calledHooks);
        self::assertEquals('PreprintSearchIndex::submissionFilesChanged', $lastHook[0]);

        // Remove the test hook.
        HookRegistry::clear('PreprintSearchIndex::submissionFilesChanged');
    }


    //
    // Public callback methods
    //
    /**
     * Simulate a search plug-ins "update file index"
     * hook.
     *
     * @see PreprintSearchIndex::submissionFileChanged()
     */
    public function callbackUpdateFileIndex($hook, $params)
    {
        self::assertEquals('PreprintSearchIndex::submissionFileChanged', $hook);

        [$preprintId, $type, $submissionFileId] = $params;
        self::assertEquals(0, $preprintId);
        self::assertEquals(1, $type);
        self::assertEquals(2, $submissionFileId);

        // Returning "true" is required so that the default submissionMetadataChanged()
        // code won't run.
        return true;
    }

    /**
     * Simulate a search plug-ins "delete text index"
     * hook.
     *
     * @see PreprintSearchIndex::submissionFileDeleted()
     */
    public function callbackDeleteTextIndex($hook, $params)
    {
        self::assertEquals('PreprintSearchIndex::submissionFileDeleted', $hook);

        [$preprintId, $type, $assocId] = $params;
        self::assertEquals(0, $preprintId);
        self::assertEquals(1, $type);
        self::assertEquals(2, $assocId);

        // Returning "true" is required so that the default submissionMetadataChanged()
        // code won't run.
        return true;
    }

    /**
     * Simulate a search plug-ins "rebuild index" hook.
     *
     * @see PreprintSearchIndex::rebuildIndex()
     */
    public function callbackRebuildIndex($hook, $params)
    {
        self::assertEquals('PreprintSearchIndex::rebuildIndex', $hook);

        [$log] = $params;
        if ($log) {
            echo 'Some log message from the plug-in.';
        }

        // Returning "true" is required so that the default rebuildIndex()
        // code won't run.
        return true;
    }

    /**
     * Simulate a search plug-ins "index preprint metadata"
     * hook.
     *
     * @see PreprintSearchIndex::submissionMetadataChanged()
     */
    public function callbackIndexPreprintMetadata($hook, $params)
    {
        self::assertEquals('PreprintSearchIndex::preprintMetadataChanged', $hook);

        [$preprint] = $params;
        self::assertInstanceOf('\APP\submission\Submission', $preprint);

        // Returning "true" is required so that the default submissionMetadataChanged()
        // code won't run.
        return true;
    }

    /**
     * Simulate a search plug-ins "index preprint files"
     * hook.
     *
     * @see PreprintSearchIndex::submissionFilesChanged()
     */
    public function callbackIndexSubmissionFiles($hook, $params)
    {
        self::assertEquals('PreprintSearchIndex::submissionFilesChanged', $hook);

        [$preprint] = $params;
        self::assertInstanceOf('\APP\submission\Submission', $preprint);

        // Returning "true" is required so that the default submissionMetadataChanged()
        // code won't run.
        return true;
    }


    //
    // Private helper methods
    //
    /**
     * Mock and register an PreprintSearchDAO as a test
     * back end for the PreprintSearchIndex class.
     */
    private function registerMockPreprintSearchDAO($clearIndexExpected, $deletePreprintExpected)
    {
        // Mock an PreprintSearchDAO.
        $preprintSearchDao = $this->getMockBuilder(PreprintSearchDAO::class)
            ->setMethods(['clearIndex', 'deleteSubmissionKeywords'])
            ->getMock();

        // Test the clearIndex() method.
        $preprintSearchDao->expects($clearIndexExpected)
            ->method('clearIndex')
            ->will($this->returnValue(null));

        // Test the deleteSubmissionKeywords() method.
        $preprintSearchDao->expects($deletePreprintExpected)
            ->method('deleteSubmissionKeywords')
            ->will($this->returnValue(null));

        // Register the mock DAO.
        DAORegistry::registerDAO('PreprintSearchDAO', $preprintSearchDao);
    }

    /**
     * Mock and register a ServerDAO as a test
     * back end for the PreprintSearchIndex class.
     */
    private function registerMockServerDAO()
    {
        // Mock a ServerDAO.
        $serverDao = $this->getMockBuilder(ServerDAO::class)
            ->setMethods(['getAll'])
            ->getMock();

        // Mock an empty result set.
        $servers = [];
        $serversIterator = new ArrayItemIterator($servers);

        // Mock the getById() method.
        $serverDao->expects($this->any())
            ->method('getAll')
            ->will($this->returnValue($serversIterator));

        // Register the mock DAO.
        DAORegistry::registerDAO('ServerDAO', $serverDao);
    }

    /**
     * Mock and register an GalleyDAO as a test back end for
     * the PreprintSearchIndex class.
     */
    private function registerFileDAOs($expectMethodCall)
    {
        // Mock file DAOs.
        App::instance(GalleyDAO::class, \Mockery::mock(GalleyDAO::class, function ($mock) use ($expectMethodCall) {
            if ($expectMethodCall) {
                $mock->shouldReceive('getBySubmissionId')->andReturn([]);
            } else {
                $mock->shouldNotReceive('getBySubmissionId');
            }
        }));

        // FIXME: GalleyDAO::getBySubmissionId returns iterator; array expected here. Fix expectations.
    }

    /**
     * Mock an PreprintSearchIndex implementation.
     *
     * @return PreprintSearchIndex
     */
    private function getMockPreprintSearchIndex($expectedCall)
    {
        // Mock PreprintSearchIndex.
        /** @var PreprintSearchIndex $preprintSearchIndex */
        $preprintSearchIndex = $this->getMockBuilder(PreprintSearchIndex::class)
            ->setMethods(['_updateTextIndex'])
            ->getMock();

        // Check for _updateTextIndex() calls.
        $preprintSearchIndex->expects($expectedCall)
            ->method('_updateTextIndex')
            ->will($this->returnValue(null));
        return $preprintSearchIndex;
    }
}
