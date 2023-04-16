<?php

/**
 * @file tests/classes/search/PreprintSearchTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintSearchTest
 *
 * @ingroup tests_classes_search
 *
 * @see PreprintSearch
 *
 * @brief Test class for the PreprintSearch class
 */

namespace APP\tests\classes\search;

use APP\core\Application;
use APP\core\PageRouter;
use APP\search\PreprintSearch;
use APP\search\PreprintSearchDAO;
use APP\server\Server;
use APP\server\ServerDAO;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

class PreprintSearchTest extends PKPTestCase
{
    private const SUBMISSION_SEARCH_TEST_DEFAULT_PREPRINT = 1;

    private array $_retrieveResultsParams;

    //
    // Implementing protected template methods from PKPTestCase
    //
    /**
     * @see PKPTestCase::getMockedDAOs()
     */
    protected function getMockedDAOs(): array
    {
        return [...parent::getMockedDAOs(), 'PreprintSearchDAO', 'ServerDAO'];
    }

    /**
     * @see PKPTestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();
        Hook::rememberCalledHooks();

        // Prepare the mock environment for this test.
        $this->registerMockPreprintSearchDAO();
        $this->registerMockServerDAO();

        $request = Application::get()->getRequest();
        if (is_null($request->getRouter())) {
            $router = new PageRouter();
            $request->setRouter($router);
        }
    }

    /**
     * @see PKPTestCase::tearDown()
     */
    protected function tearDown(): void
    {
        Hook::resetCalledHooks();
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
        // Make sure that no hook is being called.
        Hook::clear('SubmissionSearch::retrieveResults');

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
        self::assertEquals(self::SUBMISSION_SEARCH_TEST_DEFAULT_PREPRINT, $firstResult['preprint']->getId());
        self::assertEquals('', $error);
    }

    /**
     * @covers PreprintSearch
     */
    public function testRetrieveResultsViaPluginHook()
    {
        // Diverting a search to the search plugin hook.
        Hook::add('SubmissionSearch::retrieveResults', [$this, 'callbackRetrieveResults']);

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
            Hook::resetCalledHooks(true);
            $searchResult = $preprintSearch->retrieveResults($request, $server, $keywords, $error, $testFromDate, $testToDate);

            // Check the parameters passed into the callback.
            foreach ([
                $server, $testCase, $testFromDate, $testToDate, $orderBy = 'score', $orderDir = 'desc',
                $exclude = [], $page = 1, $itemsPerPage = 20, $totalResults = 3, $error = '',
                //the last item, the result,  will be checked later on
            ] as $position => $expected) {
                self::assertEquals($expected, $this->_retrieveResultsParams[$position]);
            }

            // Test the call history of the hook registry.
            $calledHooks = Hook::getCalledHooks();
            self::assertCount(1, array_filter($calledHooks, fn ($hook) => $hook[0] === 'SubmissionSearch::retrieveResults'));

            // Test whether the result from the hook is being returned.
            self::assertInstanceOf('VirtualArrayIterator', $searchResult);

            // Test the total count.
            self::assertEquals(3, $searchResult->getCount());

            // Test the search result.
            $firstResult = $searchResult->next();
            self::assertArrayHasKey('preprint', $firstResult);
            self::assertEquals(self::SUBMISSION_SEARCH_TEST_DEFAULT_PREPRINT, $firstResult['preprint']->getId());
            self::assertEquals('', $error);
        }

        // Remove the test hook.
        Hook::clear('SubmissionSearch::retrieveResults');
    }


    //
    // Public callback methods
    //
    /**
     * Simulate a search plug-ins "retrieve results" hook.
     *
     * @see SubmissionSearch::retrieveResults()
     */
    public function callbackRetrieveResults($hook, $params): bool
    {
        // Save the test parameters
        $this->_retrieveResultsParams = $params;

        // Test returning count by-ref.
        $totalCount = & $params[9];
        $totalCount = 3;

        // Mock a result set and return it.
        $results = & $params[11];
        $results = [3 => self::SUBMISSION_SEARCH_TEST_DEFAULT_PREPRINT];
        return true;
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
        $preprintSearchDao = $this->getMockBuilder(PreprintSearchDAO::class)
            ->onlyMethods(['getPhraseResults'])
            ->getMock();

        // Mock a result set.
        $searchResult = [
            self::SUBMISSION_SEARCH_TEST_DEFAULT_PREPRINT => [
                'count' => 3,
                'server_id' => 2,
                'publicationDate' => '2013-05-01 20:30:00'
            ]
        ];

        // Mock the getPhraseResults() method.
        $preprintSearchDao->expects($this->any())
            ->method('getPhraseResults')
            ->will($this->returnValue($searchResult));

        // Register the mock DAO.
        DAORegistry::registerDAO('PreprintSearchDAO', $preprintSearchDao);
    }

    /**
     * Mock and register an ServerDAO as a test
     * back end for the PreprintSearch class.
     */
    private function registerMockServerDAO()
    {
        // Mock a ServerDAO.
        $serverDao = $this->getMockBuilder(ServerDAO::class)
            ->onlyMethods(['getById'])
            ->getMock();

        // Mock a server.
        $server = new Server();
        $server->setId(1);

        // Mock the getById() method.
        $serverDao->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($server));

        // Register the mock DAO.
        DAORegistry::registerDAO('ServerDAO', $serverDao);
    }
}
