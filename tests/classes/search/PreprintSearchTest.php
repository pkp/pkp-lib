<?php

/**
 * @file tests/classes/search/PreprintSearchTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintSearchTest
 * @ingroup tests_classes_search
 *
 * @see PreprintSearch
 *
 * @brief Test class for the PreprintSearch class
 */

import('lib.pkp.tests.PKPTestCase');
import('classes.search.PreprintSearch');
import('lib.pkp.classes.core.PKPRouter');

define('SUBMISSION_SEARCH_TEST_DEFAULT_PREPRINT', 1);
define('SUBMISSION_SEARCH_TEST_PREPRINT_FROM_PLUGIN', 2);

use APP\server\Server;

class PreprintSearchTest extends PKPTestCase
{
    /** @var array */
    private $_retrieveResultsParams;

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
            'PreprintSearchDAO',
            'ServerDAO', 'SectionDAO'
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

        // Prepare the mock environment for this test.
        $this->registerMockPreprintSearchDAO();
        $this->registerMockServerDAO();
        $this->registerMockSectionDAO();

        $request = Application::get()->getRequest();
        if (is_null($request->getRouter())) {
            $router = new PKPRouter();
            $request->setRouter($router);
        }
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
     * @covers PreprintSearch
     */
    public function testRetrieveResults()
    {
        $this->markTestSkipped(); // Temporarily disabled!

        // Make sure that no hook is being called.
        HookRegistry::clear('SubmissionSearch::retrieveResults');

        // Test a simple search with a mock database back-end.
        $server = new Server();
        $keywords = [null => 'test'];
        $preprintSearch = new PreprintSearch();
        $error = '';
        $request = Application::get()->getRequest();
        $searchResult = $preprintSearch->retrieveResults($request, $server, $keywords, $error);

        // Test whether the result from the mocked DAOs is being returned.
        self::assertInstanceOf('ItemIterator', $searchResult);
        $firstResult = $searchResult->next();
        self::assertArrayHasKey('preprint', $firstResult);
        self::assertEquals(SUBMISSION_SEARCH_TEST_DEFAULT_PREPRINT, $firstResult['preprint']->getId());
        self::assertEquals('', $error);

        $this->registerMockPreprintSearchDAO(); // This is necessary to instantiate a fresh iterator.
        $keywords = [null => 'test'];
        $searchResult = $preprintSearch->retrieveResults($request, $server, $keywords, $error);
        self::assertTrue($searchResult->eof());
    }

    /**
     * @covers PreprintSearch
     */
    public function testRetrieveResultsViaPluginHook()
    {
        $this->markTestSkipped(); // Temporarily disabled!

        // Diverting a search to the search plugin hook.
        HookRegistry::register('SubmissionSearch::retrieveResults', [$this, 'callbackRetrieveResults']);

        $testCases = [
            [null => 'query'], // Simple Search - "All"
            ['1' => 'author'], // Simple Search - "Authors"
            ['2' => 'title'], // Simple Search - "Title"
            [
                null => 'query',
                1 => 'author',
                2 => 'title'
            ], // Advanced Search
        ];

        $testFromDate = date('Y-m-d H:i:s', strtotime('2011-03-15 00:00:00'));
        $testToDate = date('Y-m-d H:i:s', strtotime('2012-03-15 18:30:00'));
        $error = '';

        $request = Application::get()->getRequest();

        foreach ($testCases as $testCase) {
            // Test a simple search with the simulated callback.
            $server = new Server();
            $keywords = $testCase;
            $preprintSearch = new PreprintSearch();
            $searchResult = $preprintSearch->retrieveResults($request, $server, $keywords, $error, $testFromDate, $testToDate);

            // Check the parameters passed into the callback.
            $expectedPage = 1;
            $expectedItemsPerPage = 20;
            $expectedTotalResults = 3;
            $expectedError = '';
            $expectedParams = [
                $server, $testCase, $testFromDate, $testToDate,
                $expectedPage, $expectedItemsPerPage, $expectedTotalResults,
                $expectedError
            ];
            self::assertEquals($expectedParams, $this->_retrieveResultsParams);

            // Test and clear the call history of the hook registry.
            $calledHooks = HookRegistry::getCalledHooks();
            self::assertEquals('SubmissionSearch::retrieveResults', $calledHooks[0][0]);
            HookRegistry::resetCalledHooks(true);

            // Test whether the result from the hook is being returned.
            self::assertInstanceOf('VirtualArrayIterator', $searchResult);

            // Test the total count.
            self::assertEquals(3, $searchResult->getCount());

            // Test the search result.
            $firstResult = $searchResult->next();
            self::assertArrayHasKey('preprint', $firstResult);
            self::assertEquals(SUBMISSION_SEARCH_TEST_PREPRINT_FROM_PLUGIN, $firstResult['preprint']->getId());
            self::assertEquals('', $error);
        }

        // Remove the test hook.
        HookRegistry::clear('SubmissionSearch::retrieveResults');
    }


    //
    // Public callback methods
    //
    /**
     * Simulate a search plug-ins "retrieve results" hook.
     *
     * @see SubmissionSearch::retrieveResults()
     */
    public function callbackRetrieveResults($hook, $params)
    {
        // Save the test parameters
        $this->_retrieveResultsParams = $params;

        // Test returning count by-ref.
        $totalCount = & $params[6];
        $totalCount = 3;

        // Mock a result set and return it.
        $results = [
            3 => SUBMISSION_SEARCH_TEST_PREPRINT_FROM_PLUGIN
        ];
        return $results;
    }


    //
    // Private helper methods
    //
    /**
     * Mock and register an PreprintSearchDAO as a test
     * back end for the PreprintSearch class.
     */
    private function registerMockPreprintSearchDAO()
    {
        // Mock an PreprintSearchDAO.
        $preprintSearchDAO = $this->getMockBuilder(PreprintSearchDAO::class)
            ->setMethods(['getPhraseResults'])
            ->getMock();

        // Mock a result set.
        $searchResult = [
            SUBMISSION_SEARCH_TEST_DEFAULT_PREPRINT => [
                'count' => 3,
                'server_id' => 2,
                'publicationDate' => '2013-05-01 20:30:00'
            ]
        ];

        // Mock the getPhraseResults() method.
        $preprintSearchDAO->expects($this->any())
            ->method('getPhraseResults')
            ->will($this->returnValue($searchResult));

        // Register the mock DAO.
        DAORegistry::registerDAO('PreprintSearchDAO', $preprintSearchDAO);
    }

    /**
     * Mock and register an ServerDAO as a test
     * back end for the PreprintSearch class.
     */
    private function registerMockServerDAO()
    {
        // Mock a ServerDAO.
        $serverDAO = $this->getMockBuilder(ServerDAO::class)
            ->setMethods(['getById'])
            ->getMock();

        // Mock a server.
        $server = new Server();

        // Mock the getById() method.
        $serverDAO->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($server));

        // Register the mock DAO.
        DAORegistry::registerDAO('ServerDAO', $serverDAO);
    }

    /**
     * Mock and register an SectionDAO as a test
     * back end for the PreprintSearch class.
     */
    private function registerMockSectionDAO()
    {
        // Mock a SectionDAO.
        $sectionDao = $this->getMockBuilder(SectionDAO::class)
            ->setMethods(['getSection'])
            ->getMock();

        // Mock a section.
        $section = $sectionDao->newDataObject();

        // Mock the getSection() method.
        $sectionDao->expects($this->any())
            ->method('getSection')
            ->will($this->returnValue($section));

        // Register the mock DAO.
        DAORegistry::registerDAO('SectionDAO', $sectionDao);
    }
}
