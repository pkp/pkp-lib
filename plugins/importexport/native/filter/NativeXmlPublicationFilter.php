<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlPublicationFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlPublicationFilter
 *
 * @ingroup plugins_importexport_native
 *
 * @brief Class that converts a Native XML document to a set of publications.
 */

namespace APP\plugins\importexport\native\filter;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use DOMElement;
use PKP\filter\Filter;
use PKP\plugins\importexport\native\filter\PKPNativeFilterHelper;

class NativeXmlPublicationFilter extends \PKP\plugins\importexport\native\filter\NativeXmlPKPPublicationFilter
{
    /**
     * Handle an preprint import.
     * The preprint must have a valid section in order to be imported
     *
     * @param DOMElement $node
     */
    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();
        $sectionAbbrev = $node->getAttribute('section_ref');
        if ($sectionAbbrev !== '') {
            $section = Repo::section()->getCollector()->filterByContextIds([$context->getId()])->filterByAbbrevs([$sectionAbbrev])->getMany()->first();
            if (!$section) {
                $deployment->addError(Application::ASSOC_TYPE_SUBMISSION, null, __('plugins.importexport.native.error.unknownSection', ['param' => $sectionAbbrev]));
            } else {
                return parent::handleElement($node);
            }
        }
    }

    /**
     * Populate the submission object from the node, checking first for a valid section and published_date relationship
     *
     * @param Publication $publication
     * @param DOMElement $node
     *
     * @return Publication
     */
    public function populateObject($publication, $node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $sectionAbbrev = $node->getAttribute('section_ref');
        if ($sectionAbbrev !== '') {
            $section = Repo::section()
                ->getCollector()
                ->filterByContextIds([$context->getId()])
                ->filterByAbbrevs([$sectionAbbrev])
                ->getMany()
                ->first();
            if (!$section) {
                $deployment->addError(Application::ASSOC_TYPE_PUBLICATION, $publication->getId(), __('plugins.importexport.native.error.unknownSection', ['param' => $sectionAbbrev]));
            } else {
                $publication->setData('sectionId', $section->getId());
            }
        }

        return parent::populateObject($publication, $node);
    }

    /**
     * Handle an element whose parent is the submission element.
     *
     * @param DOMElement $n
     * @param Publication $publication
     */
    public function handleChildElement($n, $publication)
    {
        switch ($n->tagName) {
            case 'preprint_galley':
                $this->parsePreprintGalley($n, $publication);
                break;
            case 'pages':
                $publication->setData('pages', $n->textContent);
                break;
            case 'covers':
                $nativeFilterHelper = new PKPNativeFilterHelper();
                $nativeFilterHelper->parsePublicationCovers($this, $n, $publication);
                break;
            default:
                parent::handleChildElement($n, $publication);
        }
    }

    /**
     * Get the import filter for a given element.
     *
     * @param string $elementName Name of XML element
     *
     * @return Filter
     */
    public function getImportFilter($elementName)
    {
        $deployment = $this->getDeployment();
        $submission = $deployment->getSubmission();
        switch ($elementName) {
            case 'preprint_galley':
                $importClass = 'PreprintGalley';
                break;
            default:
                $importClass = null; // Suppress scrutinizer warn
                $deployment->addWarning(Application::ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownElement', ['param' => $elementName]));
        }
        // Caps on class name for consistency with imports, whose filter
        // group names are generated implicitly.
        $currentFilter = \PKP\plugins\importexport\PKPImportExportFilter::getFilter('native-xml=>' . $importClass, $deployment);
        return $currentFilter;
    }

    /**
     * Parse an preprint galley and add it to the publication.
     *
     * @param DOMElement $n
     * @param Publication $publication
     */
    public function parsePreprintGalley($n, $publication)
    {
        return $this->importWithXMLNode($n);
    }
}
