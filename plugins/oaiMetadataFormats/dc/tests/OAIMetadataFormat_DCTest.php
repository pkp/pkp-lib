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
 * @ingroup plugins_oaiMetadataFormats_dc_tests
 *
 * @see OAIMetadataFormat_DC
 *
 * @brief Test class for OAIMetadataFormat_DC.
 */

import('lib.pkp.tests.PKPTestCase');

use APP\facades\Repo;
use PKP\galley\DAO as GalleyDAO;
use PKP\oai\OAIRecord;

import('plugins.oaiMetadataFormats.dc.OAIMetadataFormat_DC');
import('plugins.oaiMetadataFormats.dc.OAIMetadataFormatPlugin_DC');

class OAIMetadataFormat_DCTest extends PKPTestCase
{
    /**
     * @see PKPTestCase::getMockedDAOs()
     */
    protected function getMockedDAOs()
    {
        return ['OAIDAO'];
    }

    /**
     * @see PKPTestCase::getMockedRegistryKeys()
     */
    protected function getMockedRegistryKeys()
    {
        return ['request'];
    }

    /**
     * @covers OAIMetadataFormat_DC
     * @covers Dc11SchemaPreprintAdapter
     */
    public function testToXml()
    {
        $this->markTestSkipped('Skipped because of weird class interaction with ControlledVocabDAO.');

        //
        // Create test data.
        //
        $serverId = 1;

        // Enable the DOI plugin.
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var PluginSettingsDAO $pluginSettingsDao */
        $pluginSettingsDao->updateSetting($serverId, 'doipubidplugin', 'enabled', 1);
        $pluginSettingsDao->updateSetting($serverId, 'doipubidplugin', 'enableIssueDoi', 1);
        $pluginSettingsDao->updateSetting($serverId, 'doipubidplugin', 'enablePublicationDoi', 1);
        $pluginSettingsDao->updateSetting($serverId, 'doipubidplugin', 'enableRepresentationyDoi', 1);

        // Author
        $author = new Author();
        $author->setGivenName('author-firstname', 'en_US');
        $author->setFamilyName('author-lastname', 'en_US');
        $author->setAffiliation('author-affiliation', 'en_US');
        $author->setEmail('someone@example.com');

        // Preprint
        import('classes.submission.Submission');
        $preprint = $this->getMockBuilder(Submission::class)
            ->setMethods(['getBestId'])
            ->getMock();
        $preprint->expects($this->any())
            ->method('getBestId')
            ->will($this->returnValue(9));
        $preprint->setId(9);
        $preprint->setServerId($serverId);
        $author->setSubmissionId($preprint->getId());
        $preprint->setPages(15);
        $preprint->setType('art-type', 'en_US');
        $preprint->setTitle('preprint-title-en', 'en_US');
        $preprint->setTitle('preprint-title-de', 'de_DE');
        $preprint->setDiscipline('preprint-discipline', 'en_US');
        $preprint->setSubject('preprint-subject', 'en_US');
        $preprint->setAbstract('preprint-abstract', 'en_US');
        $preprint->setSponsor('preprint-sponsor', 'en_US');
        $preprint->setStoredPubId('doi', 'preprint-doi');
        $preprint->setLanguage('en_US');

        // Galleys
        $galley = Repo::galley()->newDataObject();
        $galley->setId(98);
        $galley->setStoredPubId('doi', 'galley-doi');
        $galleys = [$galley];

        // Server
        import('classes.server.Server');
        $server = $this->getMockBuilder(Server::class)
            ->setMethods(['getSetting'])
            ->getMock();
        $server->expects($this->any())
            ->method('getSetting') // includes getTitle()
            ->will($this->returnCallback([$this, 'getServerSetting']));
        $server->setPrimaryLocale('en_US');
        $server->setPath('server-path');
        $server->setId($serverId);

        // Section
        import('classes.server.Section');
        $section = new Section();
        $section->setIdentifyType('section-identify-type', 'en_US');

        //
        // Create infrastructural support objects
        //

        // Router
        import('lib.pkp.classes.core.PKPRouter');
        $router = $this->getMockBuilder(PKPRouter::class)
            ->setMethods(['url'])
            ->getMock();
        $application = Application::get();
        $router->setApplication($application);
        $router->expects($this->any())
            ->method('url')
            ->will($this->returnCallback([$this, 'routerUrl']));

        // Request
        import('classes.core.Request');
        $request = $this->getMockBuilder(Request::class)
            ->setMethods(['getRouter'])
            ->getMock();
        $request->expects($this->any())
            ->method('getRouter')
            ->will($this->returnValue($router));
        Registry::set('request', $request);


        //
        // Create mock DAOs
        //

        // FIXME getBySubmissionId should use the publication id now.
        import('classes.preprint.AuthorDAO');
        $authorDao = $this->getMockBuilder(AuthorDAO::class)
            ->setMethods(['getBySubmissionId'])
            ->getMock();
        $authorDao->expects($this->any())
            ->method('getBySubmissionId')
            ->will($this->returnValue([$author]));
        DAORegistry::registerDAO('AuthorDAO', $authorDao);

        // Create a mocked OAIDAO that returns our test data.
        import('classes.oai.ops.OAIDAO');
        $oaiDao = $this->getMockBuilder(OAIDAO::class)
            ->setMethods(['getServer', 'getSection', 'getIssue'])
            ->getMock();
        $oaiDao->expects($this->any())
            ->method('getServer')
            ->will($this->returnValue($server));
        $oaiDao->expects($this->any())
            ->method('getSection')
            ->will($this->returnValue($section));
        $oaiDao->expects($this->any())
            ->method('getIssue')
            ->will($this->returnValue($issue));
        DAORegistry::registerDAO('OAIDAO', $oaiDao);

        // Create a mocked GalleyDAO that returns our test data.
        $galleyDao = $this->getMockBuilder(GalleyDAO::class)
            ->setMethods(['getBySubmissionId'])
            ->getMock();
        $galleyDao->expects($this->any())
            ->method('getBySubmissionId')
            ->will($this->returnValue($galleys));

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
        self::assertXmlStringEqualsXmlFile('tests/plugins/oaiMetadataFormats/dc/expectedResult.xml', $xml);
    }


    //
    // Public helper methods
    //
    /**
     * Callback for server settings.
     *
     * @param string $settingName
     */
    public function getServerSetting($settingName)
    {
        switch ($settingName) {
            case 'name':
                return ['en_US' => 'server-title'];

            case 'licenseTerms':
                return ['en_US' => 'server-copyright'];

            case 'publisherInstitution':
                return ['server-publisher'];

            case 'onlineIssn':
                return 'onlineIssn';

            case 'printIssn':
                return null;

            default:
                self::fail('Required server setting is not necessary for the purpose of this test.');
        }
    }


    //
    // Private helper methods
    //
    /**
     * Callback for router url construction simulation.
     *
     * @param null|mixed $newContext
     * @param null|mixed $handler
     * @param null|mixed $op
     * @param null|mixed $path
     */
    public function routerUrl($request, $newContext = null, $handler = null, $op = null, $path = null)
    {
        return $handler . '-' . $op . '-' . implode('-', $path);
    }
}
