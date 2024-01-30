<?php

/**
 * @file plugins/importexport/native/filter/PKPPublicationNativeXmlFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicationNativeXmlFilter
 *
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Publication to a Native XML document
 */

namespace PKP\plugins\importexport\native\filter;

use APP\core\Application;
use APP\plugins\importexport\native\NativeImportExportDeployment;
use APP\publication\Publication;
use Exception;
use PKP\citation\CitationDAO;
use PKP\db\DAORegistry;
use PKP\filter\FilterGroup;
use PKP\plugins\importexport\PKPImportExportFilter;
use PKP\plugins\PluginRegistry;
use PKP\submission\PKPSubmission;
use PKP\submission\Representation;
use PKP\submission\RepresentationDAOInterface;

class PKPPublicationNativeXmlFilter extends NativeExportFilter
{
    /**
     * Constructor
     *
     * @param FilterGroup $filterGroup
     */
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML Publication export');
        parent::__construct($filterGroup);
    }

    //
    // Implement template methods from Filter
    //
    /**
     * @see Filter::process()
     *
     * @param Publication $entity
     *
     * @return \DOMDocument
     */
    public function &process(&$entity)
    {
        // Create the XML document
        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();
        $rootNode = $this->createEntityNode($doc, $entity);
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    //
    // Representation conversion functions
    //
    /**
     * Create and return an entity node.
     *
     * @param \DOMDocument $doc
     * @param Publication $entity
     *
     * @return \DOMElement
     */
    public function createEntityNode($doc, $entity)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        // Create the entity node
        $entityNode = $doc->createElementNS($deployment->getNamespace(), 'publication');

        $this->addIdentifiers($doc, $entityNode, $entity);

        $entityNode->setAttribute('version', $entity->getData('version') ?: 1);
        $entityNode->setAttribute('status', $entity->getData('status'));
        if ($primaryContactId = $entity->getData('primaryContactId')) {
            $entityNode->setAttribute('primary_contact_id', $primaryContactId);
        }
        $entityNode->setAttribute('url_path', $entity->getData('urlPath'));

        if ($entity->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            $entityNode->setAttribute('seq', (int) $entity->getData('seq'));
        } else {
            $entityNode->setAttribute('seq', '0');
        }

        if ($entity->getData('accessStatus')) {
            $entityNode->setAttribute('access_status', $entity->getData('accessStatus'));
        } else {
            $entityNode->setAttribute('access_status', '0');
        }

        if ($datePublished = $entity->getData('datePublished')) {
            $entityNode->setAttribute('date_published', date('Y-m-d', strtotime($datePublished)));
        }

        $this->addMetadata($doc, $entityNode, $entity);

        $authors = $entity->getData('authors');
        if ($authors && count($authors) > 0) {
            $this->addAuthors($doc, $entityNode, $entity);
        }

        $this->addRepresentations($doc, $entityNode, $entity);

        $citationsListNode = $this->createCitationsNode($doc, $deployment, $entity);
        if ($citationsListNode->hasChildNodes() || $citationsListNode->hasAttributes()) {
            $entityNode->appendChild($citationsListNode);
        }

        return $entityNode;
    }

    /**
     * Create and add identifier nodes to a submission node.
     *
     * @param \DOMDocument $doc
     * @param \DOMElement $entityNode
     * @param Publication $entity
     */
    public function addIdentifiers($doc, $entityNode, $entity)
    {
        $deployment = $this->getDeployment();

        // Add internal ID
        $entityNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', $entity->getId()));
        $node->setAttribute('type', 'internal');
        $node->setAttribute('advice', 'ignore');

        // Add public ID
        if ($pubId = $entity->getStoredPubId('publisher-id')) {
            $entityNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', htmlspecialchars($pubId, ENT_COMPAT, 'UTF-8')));
            $node->setAttribute('type', 'public');
            $node->setAttribute('advice', 'update');
        }

        // Add pub IDs by plugin
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $deployment->getContext()->getId());
        foreach ($pubIdPlugins as $pubIdPlugin) {
            $this->addPubIdentifier($doc, $entityNode, $entity, $pubIdPlugin->getPubIdType());
        }
        // Also add DOI
        $this->addPubIdentifier($doc, $entityNode, $entity, 'doi');
    }

    /**
     * Add a single pub ID element for a given plugin to the document.
     *
     * @param \DOMDocument $doc
     * @param \DOMElement $entityNode
     * @param Publication $entity
     *
     * @return ?\DOMElement
     */
    public function addPubIdentifier($doc, $entityNode, $entity, $pubIdType)
    {
        $pubId = $entity->getStoredPubId($pubIdType);
        if ($pubId) {
            $deployment = $this->getDeployment();
            $entityNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', htmlspecialchars($pubId, ENT_COMPAT, 'UTF-8')));
            $node->setAttribute('type', $pubIdType);
            $node->setAttribute('advice', 'update');
            return $node;
        }
        return null;
    }

    /**
     * Add the publication metadata for a publication to its DOM element.
     *
     * @param \DOMDocument $doc
     * @param \DOMElement $entityNode
     * @param Publication $entity
     */
    public function addMetadata($doc, $entityNode, $entity)
    {
        $deployment = $this->getDeployment();
        $this->createLocalizedNodes($doc, $entityNode, 'title', $entity->getTitles('html'));
        $this->createLocalizedNodes($doc, $entityNode, 'prefix', $entity->getData('prefix'));
        $this->createLocalizedNodes($doc, $entityNode, 'subtitle', $entity->getSubTitles('html'));
        $this->createLocalizedNodes($doc, $entityNode, 'abstract', $entity->getData('abstract'));
        $this->createLocalizedNodes($doc, $entityNode, 'coverage', $entity->getData('coverage'));
        $this->createLocalizedNodes($doc, $entityNode, 'type', $entity->getData('type'));
        $this->createLocalizedNodes($doc, $entityNode, 'source', $entity->getData('source'));
        $this->createLocalizedNodes($doc, $entityNode, 'rights', $entity->getData('rights'));

        if ($entity->getData('licenseUrl')) {
            $entityNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'licenseUrl', htmlspecialchars($entity->getData('licenseUrl'))));
        }

        $this->createLocalizedNodes($doc, $entityNode, 'copyrightHolder', $entity->getData('copyrightHolder'));

        if ($entity->getData('copyrightYear')) {
            $entityNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'copyrightYear', intval($entity->getData('copyrightYear'))));
        }

        // add controlled vocabularies
        // get the supported locale keys
        $supportedLocales = $deployment->getContext()->getSupportedFormLocales();
        $controlledVocabulariesMapping = $this->_getControlledVocabulariesMappings();
        foreach ($controlledVocabulariesMapping as $controlledVocabulariesNodeName => $mappings) {
            $dao = DAORegistry::getDAO($mappings[0]);
            $getFunction = $mappings[1];
            $controlledVocabularyNodeName = $mappings[2];
            $controlledVocabulary = $dao->$getFunction($entity->getId(), $supportedLocales);
            $this->addControlledVocabulary($doc, $entityNode, $controlledVocabulariesNodeName, $controlledVocabularyNodeName, $controlledVocabulary);
        }
    }

    /**
     * Add publication's controlled vocabulary to its DOM element.
     *
     * @param \DOMDocument $doc
     * @param \DOMElement $entityNode
     * @param string $controlledVocabulariesNodeName Parent node name
     * @param string $controlledVocabularyNodeName Item node name
     * @param array $controlledVocabulary Associative array (locale => array of items)
     */
    public function addControlledVocabulary($doc, $entityNode, $controlledVocabulariesNodeName, $controlledVocabularyNodeName, $controlledVocabulary)
    {
        $deployment = $this->getDeployment();
        $locales = array_keys($controlledVocabulary);
        foreach ($locales as $locale) {
            if (!empty($controlledVocabulary[$locale])) {
                $controlledVocabulariesNode = $doc->createElementNS($deployment->getNamespace(), $controlledVocabulariesNodeName);
                $controlledVocabulariesNode->setAttribute('locale', $locale);
                foreach ($controlledVocabulary[$locale] as $controlledVocabularyItem) {
                    $controlledVocabulariesNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), $controlledVocabularyNodeName, htmlspecialchars($controlledVocabularyItem, ENT_COMPAT, 'UTF-8')));
                }

                $entityNode->appendChild($controlledVocabulariesNode);
            }
        }
    }

    /**
     * Add the author metadata for a submission to its DOM element.
     *
     * @param \DOMDocument $doc
     * @param \DOMElement $entityNode
     * @param Publication $entity
     */
    public function addAuthors($doc, $entityNode, $entity)
    {
        $currentFilter = PKPImportExportFilter::getFilter('author=>native-xml', $this->getDeployment());

        $authors = $entity->getData('authors')->toArray();
        $authorsDoc = $currentFilter->execute($authors);

        if ($authorsDoc && $authorsDoc->documentElement instanceof \DOMElement) {
            $clone = $doc->importNode($authorsDoc->documentElement, true);
            $entityNode->appendChild($clone);
        } else {
            $deployment = $this->getDeployment();
            $deployment->addError(Application::ASSOC_TYPE_PUBLICATION, $entity->getId(), __('plugins.importexport.author.exportFailed'));

            throw new Exception(__('plugins.importexport.author.exportFailed'));
        }
    }

    /**
     * Add the representations of a publication to its DOM element.
     *
     * @param \DOMDocument $doc
     * @param \DOMElement $entityNode
     * @param Publication $entity
     */
    public function addRepresentations($doc, $entityNode, $entity)
    {
        $currentFilter = PKPImportExportFilter::getFilter($this->getRepresentationExportFilterGroupName(), $this->getDeployment());

        /** @var RepresentationDAOInterface $representationDao */
        $representationDao = Application::getRepresentationDAO();
        $representations = $representationDao->getByPublicationId($entity->getId());
        foreach ($representations as $representation) {
            $representationDoc = $currentFilter->execute($representation);
            $clone = $doc->importNode($representationDoc->documentElement, true);
            $entityNode->appendChild($clone);
        }
    }

    /**
     * Get controlled vocabularies parent node name to DAO, get function and item node name mapping.
     *
     * @return array
     */
    public function _getControlledVocabulariesMappings()
    {
        return [
            'keywords' => ['SubmissionKeywordDAO', 'getKeywords', 'keyword'],
            'agencies' => ['SubmissionAgencyDAO', 'getAgencies', 'agency'],
            'disciplines' => ['SubmissionDisciplineDAO', 'getDisciplines', 'discipline'],
            'subjects' => ['SubmissionSubjectDAO', 'getSubjects', 'subject'],
        ];
    }

    //
    // Abstract methods to be implemented by subclasses
    //
    /**
     * Get the submission files associated with this representation
     *
     * @param Representation $representation
     *
     * @return array
     */
    public function getFiles($representation)
    {
        assert(false); // To be overridden by subclasses
    }

    /**
     * Create and return a Citations node.
     *
     * @param \DOMDocument $doc
     * @param NativeImportExportDeployment $deployment
     * @param Publication $publication
     *
     * @return \DOMElement
     */
    private function createCitationsNode($doc, $deployment, $publication)
    {
        $citationDao = DAORegistry::getDAO('CitationDAO'); /** @var CitationDAO $citationDao */

        $nodeCitations = $doc->createElementNS($deployment->getNamespace(), 'citations');
        $submissionCitations = $citationDao->getByPublicationId($publication->getId())->toAssociativeArray();

        foreach ($submissionCitations as $submissionCitation) {
            $rawCitation = $submissionCitation->getRawCitation();
            $nodeCitations->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'citation', htmlspecialchars($rawCitation, ENT_COMPAT, 'UTF-8')));
        }

        return $nodeCitations;
    }
}
