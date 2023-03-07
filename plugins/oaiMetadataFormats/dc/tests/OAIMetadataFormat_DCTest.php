<?php

/**
 * @defgroup plugins_oaiMetadataFormats_dc_tests Dublin Core OAI Plugin
 */

/**
 * @file plugins/oaiMetadataFormats/dc/tests/OAIMetadataFormat_DCTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAIMetadataFormat_DCTest
 *
 * @ingroup plugins_oaiMetadataFormats_dc_tests
 *
 * @see OAIMetadataFormat_DC
 *
 * @brief Test class for OAIMetadataFormat_DC.
 */

namespace APP\plugins\oaiMetadataFormats\dc\tests;

use APP\author\Author;
use APP\core\Application;
use APP\core\PageRouter;
use APP\core\Request;
use APP\facades\Repo;
use APP\oai\ops\OAIDAO;
use APP\plugins\oaiMetadataFormats\dc\OAIMetadataFormat_DC;
use APP\plugins\oaiMetadataFormats\dc\OAIMetadataFormatPlugin_DC;
use APP\publication\Publication;
use APP\section\Section;
use APP\server\Server;
use APP\submission\Submission;
use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\doi\Doi;
use PKP\galley\Collector as GalleyCollector;
use PKP\galley\Galley;
use PKP\oai\OAIRecord;
use PKP\submission\SubmissionKeywordDAO;
use PKP\submission\SubmissionSubjectDAO;
use PKP\tests\PKPTestCase;

class OAIMetadataFormat_DCTest extends PKPTestCase
{
    /**
     * @see PKPTestCase::getMockedDAOs()
     */
    protected function getMockedDAOs(): array
    {
        return [...parent::getMockedDAOs(), 'OAIDAO', 'SubmissionSubjectDAO', 'SubmissionKeywordDAO'];
    }

    /**
     * @see PKPTestCase::getMockedRegistryKeys()
     */
    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'request'];
    }

    /**
     * @covers OAIMetadataFormat_DC
     * @covers \APP\plugins\metadata\dc11\filter\Dc11SchemaPreprintAdapter
     */
    public function testToXml()
    {
        //
        // Create test data.
        //
        $serverId = 1;

        // Publication
        /** @var Doi|MockObject */
        $publicationDoiObject = $this->getMockBuilder(Doi::class)
            ->onlyMethods([])
            ->getMock();
        $publicationDoiObject->setData('doi', 'preprint-doi');

        // Author
        $author = new Author();
        $author->setGivenName('author-firstname', 'en');
        $author->setFamilyName('author-lastname', 'en');
        $author->setAffiliation('author-affiliation', 'en');
        $author->setEmail('someone@example.com');

        /** @var Publication|MockObject */
        $publication = $this->getMockBuilder(Publication::class)
            ->onlyMethods([])
            ->getMock();
        $publication->setData('pages', 15);
        $publication->setData('type', 'art-type', 'en');
        $publication->setData('title', 'preprint-title-en', 'en');
        $publication->setData('title', 'preprint-title-de', 'de');
        $publication->setData('coverage', ['en' => ['preprint-coverage-geo', 'preprint-coverage-chron', 'preprint-coverage-sample']]);
        $publication->setData('abstract', 'preprint-abstract', 'en');
        $publication->setData('sponsor', 'preprint-sponsor', 'en');
        $publication->setData('doiObject', $publicationDoiObject);
        $publication->setData('languages', 'en');
        $publication->setData('copyrightHolder', 'preprint-copyright');
        $publication->setData('copyrightYear', 'year');
        $publication->setData('datePublished', '2010-11-05');
        $publication->setData('authors', collect([$author]));

        // Preprint
        /** @var Submission|MockObject */
        $preprint = $this->getMockBuilder(Submission::class)
            ->onlyMethods(['getBestId', 'getCurrentPublication'])
            ->getMock();
        $preprint->expects($this->any())
            ->method('getBestId')
            ->will($this->returnValue(9));
        $preprint->setId(9);
        $preprint->setData('contextId', $serverId);
        $author->setSubmissionId($preprint->getId());
        $preprint->expects($this->any())
            ->method('getCurrentPublication')
            ->will($this->returnValue($publication));

        /** @var Doi|MockObject */
        $galleyDoiObject = $this->getMockBuilder(Doi::class)
            ->onlyMethods([])
            ->getMock();
        $galleyDoiObject->setData('doi', 'galley-doi');

        // Galleys
        $galley = Repo::galley()->newDataObject();
        /** @var Galley|MockObject */
        $galley = $this->getMockBuilder(Galley::class)
            ->onlyMethods(['getFileType', 'getBestGalleyId'])
            ->setProxyTarget($galley)
            ->getMock();
        $galley->expects(self::any())
            ->method('getFileType')
            ->will($this->returnValue('galley-filetype'));
        $galley->expects(self::any())
            ->method('getBestGalleyId')
            ->will($this->returnValue(98));
        $galley->setId(98);
        $galley->setData('doiObject', $galleyDoiObject);

        $galleys = [$galley];

        // Server
        /** @var Server|MockObject */
        $server = $this->getMockBuilder(Server::class)
            ->onlyMethods(['getSetting'])
            ->getMock();
        $server->expects($this->any())
            ->method('getSetting')
            ->with('publishingMode')
            ->will($this->returnValue(\APP\server\Server::PUBLISHING_MODE_OPEN));
        $server->setName('server-title', 'en');
        $server->setData('publisherInstitution', 'server-publisher');
        $server->setPrimaryLocale('en');
        $server->setPath('server-path');
        $server->setData('onlineIssn', 'onlineIssn');
        $server->setData('printIssn', null);
        $server->setData(Server::SETTING_ENABLE_DOIS, true);
        $server->setId($serverId);

        // Section
        $section = new Section();
        $section->setIdentifyType('section-identify-type', 'en');

        //
        // Create infrastructural support objects
        //

        // Router
        /** @var PageRouter|MockObject */
        $router = $this->getMockBuilder(PageRouter::class)
            ->onlyMethods(['url'])
            ->getMock();
        $application = Application::get();
        $router->setApplication($application);
        $router->expects($this->any())
            ->method('url')
            ->will($this->returnCallback(fn ($request, $newContext = null, $handler = null, $op = null, $path = null) => $handler . '-' . $op . '-' . implode('-', $path)));

        // Request
        $requestMock = $this->getMockBuilder(Request::class)
            ->onlyMethods(['getRouter'])
            ->getMock();
        $requestMock->expects($this->any())
            ->method('getRouter')
            ->will($this->returnValue($router));
        Registry::set('request', $requestMock);


        //
        // Create mock DAOs
        //

        // Create a mocked OAIDAO that returns our test data.
        $oaiDao = $this->getMockBuilder(OAIDAO::class)
            ->onlyMethods(['getServer', 'getSection'])
            ->getMock();
        $oaiDao->expects($this->any())
            ->method('getServer')
            ->will($this->returnValue($server));
        $oaiDao->expects($this->any())
            ->method('getSection')
            ->will($this->returnValue($section));
        DAORegistry::registerDAO('OAIDAO', $oaiDao);

        /** @var GalleyCollector|MockObject */
        $mockGalleyCollector = $this->getMockBuilder(GalleyCollector::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMany'])
            ->getMock();
        $mockGalleyCollector->expects($this->any())
            ->method('getMany')
            ->will($this->returnValue(LazyCollection::wrap($galleys)));
        app()->instance(GalleyCollector::class, $mockGalleyCollector);

        // Mocked DAO to return the subjects
        $submissionSubjectDao = $this->getMockBuilder(SubmissionSubjectDAO::class)
            ->onlyMethods(['getSubjects'])
            ->getMock();
        $submissionSubjectDao->expects($this->any())
            ->method('getSubjects')
            ->will($this->returnValue(['en' => ['preprint-subject', 'preprint-subject-class']]));
        DAORegistry::registerDAO('SubmissionSubjectDAO', $submissionSubjectDao);

        // Mocked DAO to return the keywords
        $submissionKeywordDao = $this->getMockBuilder(SubmissionKeywordDAO::class)
            ->onlyMethods(['getKeywords'])
            ->getMock();
        $submissionKeywordDao->expects($this->any())
            ->method('getKeywords')
            ->will($this->returnValue(['en' => ['preprint-keyword']]));
        DAORegistry::registerDAO('SubmissionKeywordDAO', $submissionKeywordDao);

        //
        // Test
        //

        // OAI record
        $record = new OAIRecord();
        $record->setData('preprint', $preprint);
        $record->setData('galleys', $galleys);
        $record->setData('server', $server);
        $record->setData('section', $section);
        $record->setData('issue', $issue);

        // Instantiate the OAI meta-data format.
        $prefix = OAIMetadataFormatPlugin_DC::getMetadataPrefix();
        $schema = OAIMetadataFormatPlugin_DC::getSchema();
        $namespace = OAIMetadataFormatPlugin_DC::getNamespace();
        $mdFormat = new OAIMetadataFormat_DC($prefix, $schema, $namespace);

        $xml = $mdFormat->toXml($record);
        self::assertXmlStringEqualsXmlFile('plugins/oaiMetadataFormats/dc/tests/expectedResult.xml', $xml);
    }
}
