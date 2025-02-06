<?php

/**
 * @file plugins/importexport/native/filter/PKPAuthorNativeXmlFilter.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorNativeXmlFilter
 *
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a set of authors to a Native XML document
 */

namespace PKP\plugins\importexport\native\filter;

use APP\core\Application;
use APP\facades\Repo;
use Exception;
use PKP\filter\FilterGroup;

class PKPAuthorNativeXmlFilter extends NativeExportFilter
{
    /**
     * Constructor
     *
     * @param FilterGroup $filterGroup
     */
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML author export');
        parent::__construct($filterGroup);
    }

    //
    // Implement template methods from Filter
    //
    /**
     * @see Filter::process()
     *
     * @param array $authors Array of authors
     *
     * @return \DOMDocument
     */
    public function &process(&$authors)
    {
        // Create the XML document
        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();

        // Multiple authors; wrap in a <authors> element
        $rootNode = $doc->createElementNS($deployment->getNamespace(), 'authors');
        foreach ($authors as $author) {
            $rootNode->appendChild($this->createPKPAuthorNode($doc, $author));
        }
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    //
    // PKPAuthor conversion functions
    //
    /**
     * Create and return an author node.
     *
     * @param \DOMDocument $doc
     * @param \PKP\author\Author $author
     *
     * @return \DOMElement
     */
    public function createPKPAuthorNode($doc, $author)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $submission = $deployment->getSubmission();

        // Create the author node
        $authorNode = $doc->createElementNS($deployment->getNamespace(), 'author');

        if ($author->getPrimaryContact()) {
            $authorNode->setAttribute('primary_contact', 'true');
        }
        if ($author->getIncludeInBrowse()) {
            $authorNode->setAttribute('include_in_browse', 'true');
        }

        $userGroup = Repo::userGroup()->get($author->getUserGroupId());
        assert(isset($userGroup));

        if (!$userGroup) {
            $deployment->addError(Application::ASSOC_TYPE_AUTHOR, $author->getId(), __('plugins.importexport.common.error.userGroupMissing', ['param' => $author->getFullName()]));
            throw new Exception(__('plugins.importexport.author.exportFailed'));
        }

        $authorNode->setAttribute('user_group_ref', $userGroup->getLocalizedData('name', $context->getPrimaryLocale()));
        $authorNode->setAttribute('seq', $author->getSequence());

        $authorNode->setAttribute('id', $author->getId());

        // Add metadata
        $this->createLocalizedNodes($doc, $authorNode, 'givenname', $author->getGivenName(null));
        $this->createLocalizedNodes($doc, $authorNode, 'familyname', $author->getFamilyName(null));

        foreach ($author->getAffiliations() as $affiliation) {
            if ($affiliation->getRorObject()) {
                $rorAffiliationNode = $doc->createElementNS($deployment->getNamespace(), 'rorAffiliation');
                $rorAffiliationRor = $doc->createElementNS($deployment->getNamespace(), 'ror', $affiliation->getRor());
                $rorAffiliationNode->appendChild($rorAffiliationRor);
                // The rorAffiliation->name element is only read only ie. it will not be considered at import.
                // Export the ror names mapped to all allowed submission locales
                // Eventually to export only the ror name mapped to the submission primary locale ?
                // Or the ror names as they are, with the ror locales ?
                $allowedLocales = $submission->getPublicationLanguages($context->getSupportedSubmissionMetadataLocales());
                $this->createLocalizedNodes($doc, $rorAffiliationNode, 'name', $affiliation->getAffiliationName(null, $allowedLocales));
                $authorNode->appendChild($rorAffiliationNode);
            } elseif (!empty($affiliation->getName())) {
                $affiliationNode = $doc->createElementNS($deployment->getNamespace(), 'affiliation');
                $this->createLocalizedNodes($doc, $affiliationNode, 'name', $affiliation->getName());
                $authorNode->appendChild($affiliationNode);
            }
        }

        $this->createOptionalNode($doc, $authorNode, 'country', $author->getCountry());
        $authorNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'email', htmlspecialchars($author->getEmail(), ENT_COMPAT, 'UTF-8')));
        $this->createOptionalNode($doc, $authorNode, 'url', $author->getUrl());
        $this->createOptionalNode($doc, $authorNode, 'orcid', $author->getOrcid());

        $this->createLocalizedNodes($doc, $authorNode, 'biography', $author->getBiography(null));

        return $authorNode;
    }
}
