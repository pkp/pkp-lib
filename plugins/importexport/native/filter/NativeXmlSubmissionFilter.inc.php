<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlSubmissionFilter.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlSubmissionFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of submissions
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

use APP\facades\Repo;
use APP\submission\Submission;
use PKP\observers\events\BatchMetadataChanged;
use PKP\workflow\WorkflowStageDAO;

class NativeXmlSubmissionFilter extends NativeImportFilter
{
    /**
     * Constructor
     *
     * @param FilterGroup $filterGroup
     */
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML submission import');
        parent::__construct($filterGroup);
    }


    //
    // Implement template methods from PersistableFilter
    //
    /**
     * @copydoc PersistableFilter::getClassName()
     */
    public function getClassName()
    {
        return 'lib.pkp.plugins.importexport.native.filter.NativeXmlSubmissionFilter';
    }


    //
    // Implement template methods from NativeImportFilter
    //
    /**
     * Return the plural element name
     *
     * @return string
     */
    public function getPluralElementName()
    {
        $deployment = $this->getDeployment();
        return $deployment->getSubmissionsNodeName();
    }

    /**
     * Get the singular element name
     *
     * @return string
     */
    public function getSingularElementName()
    {
        $deployment = $this->getDeployment();
        return $deployment->getSubmissionNodeName();
    }

    /**
     * Handle a singular element import.
     *
     * @param DOMElement $node
     */
    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        // Create and insert the submission (ID needed for other entities)
        $submission = Repo::submission()->newDataObject();

        $submission->setData('contextId', $context->getId());
        $submission->stampLastActivity();
        $submission->setData('status', $node->getAttribute('status'));
        $submission->setData('submissionProgress', 0);

        $submission->setData('stageId', WorkflowStageDAO::getIdFromPath($node->getAttribute('stage')));
        $submission->setData('currentPublicationId', $node->getAttribute('current_publication_id'));

        // Handle any additional attributes etc.
        $submission = $this->populateObject($submission, $node);

        $submissionId = Repo::submission()->dao->insert($submission);
        $submission = Repo::submission()->get($submissionId);
        $deployment->setSubmission($submission);
        $deployment->addProcessedObjectId(ASSOC_TYPE_SUBMISSION, $submission->getId());

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof \DOMElement) {
                $this->handleChildElement($n, $submission);
            }
        }

        $submission = Repo::submission()->get($submission->getId());

        $deployment->addImportedRootEntity(ASSOC_TYPE_SUBMISSION, $submission);

        return $submission;
    }

    /**
     * Populate the submission object from the node
     *
     * @param Submission $submission
     * @param DOMElement $node
     *
     * @return Submission
     */
    public function populateObject($submission, $node)
    {
        if ($dateSubmitted = $node->getAttribute('date_submitted')) {
            $submission->setData('dateSubmitted', Core::getCurrentDate(strtotime($dateSubmitted)));
        } else {
            $submission->setData('dateSubmitted', Core::getCurrentDate());
        }

        return $submission;
    }

    /**
     * Handle an element whose parent is the submission element.
     *
     * @param DOMElement $n
     * @param Submission $submission
     */
    public function handleChildElement($n, $submission)
    {
        switch ($n->tagName) {
            case 'id':
                $this->parseIdentifier($n, $submission);
                break;
            case 'submission_file':
                $this->parseChild($n, $submission);
                break;
            case 'publication':
                $this->parseChild($n, $submission);
                break;
            default:
                $deployment = $this->getDeployment();
                $deployment->addWarning(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownElement', ['param' => $n->tagName]));
        }
    }

    //
    // Element parsing
    //
    /**
     * Parse an identifier node and set up the submission object accordingly
     *
     * @param DOMElement $element
     * @param Submission $submission
     */
    public function parseIdentifier($element, $submission)
    {
        $deployment = $this->getDeployment();
        $advice = $element->getAttribute('advice');
        switch ($element->getAttribute('type')) {
            case 'internal':
                // "update" advice not supported yet.
                assert(!$advice || $advice == 'ignore');
                break;
        }
    }

    /**
     * @see Filter::process()
     *
     * @param DOMDocument|string $document
     *
     * @return array Array of imported documents
     */
    public function &process(&$document)
    {
        $importedObjects =& parent::process($document);

        $deployment = $this->getDeployment();

        // Index imported content
        $submissionIds = [];
        foreach ($importedObjects as $submission) {
            assert($submission instanceof Submission);
            $publication = $submission->getCurrentPublication();
            if (!isset($publication)) {
                $deployment->addError(ASSOC_TYPE_SUBMISSION, $submission->getId(),  __('plugins.importexport.common.error.currentPublicationNullOrMissing'));
            }
            $submissionIds[] = $submission->getId();
        }

        event(new BatchMetadataChanged($submissionIds));

        return $importedObjects;
    }

    /**
     * Parse a submission child and add it to the submission.
     *
     * @param DOMElement $n
     * @param Submission $submission
     */
    function parseChild($n, $submission) 
    {
        $importFilter = $this->getImportFilter($n->tagName);
        assert(isset($importFilter)); // There should be a filter

        $submissionChildDoc = new DOMDocument();
        $submissionChildDoc->appendChild($submissionChildDoc->importNode($n, true));
        $ret = $importFilter->execute($submissionChildDoc);

        if ($ret == null) {
            $deployment = $this->getDeployment();

            $deployment->addError(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.submissionChildFailed', ['child' => $n->tagName]));
        }
    }

    //
    // Helper functions
    //

    /**
     * Get the import filter for a given element.
     *
     * @param string $elementName Name of XML element
     *
     * @return Filter
     */
    public function getImportFilter($elementName)
    {
        assert(false); // Subclasses should override
    }
}
