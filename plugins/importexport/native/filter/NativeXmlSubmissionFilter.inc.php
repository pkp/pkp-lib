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

use PKP\workflow\WorkflowStageDAO;

class NativeXmlSubmissionFilter extends NativeImportFilter
{
    /**
     * Constructor
     *
     * @param $filterGroup FilterGroup
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
     * @param $node DOMElement
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
     * @param $submission Submission
     * @param $node DOMElement
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
     * @param $n DOMElement
     * @param $submission Submission
     */
    public function handleChildElement($n, $submission)
    {
        switch ($n->tagName) {
            case 'id':
                $this->parseIdentifier($n, $submission);
                break;
            case 'submission_file':
                $this->parseSubmissionFile($n, $submission);
                break;
            case 'publication':
                $this->parsePublication($n, $submission);
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
     * @param $element DOMElement
     * @param $submission Submission
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
     * Parse a submission file and add it to the submission.
     *
     * @param $n DOMElement
     * @param $submission Submission
     */
    public function parseSubmissionFile($n, $submission)
    {
        $importFilter = $this->getImportFilter($n->tagName);
        assert(isset($importFilter)); // There should be a filter

        $submissionFileDoc = new DOMDocument();
        $submissionFileDoc->appendChild($submissionFileDoc->importNode($n, true));
        return $importFilter->execute($submissionFileDoc);
    }

    /**
     * @see Filter::process()
     *
     * @param $document DOMDocument|string
     *
     * @return array Array of imported documents
     */
    public function &process(&$document)
    {
        $importedObjects = & parent::process($document);

        // Index imported content
        $submissionSearchIndex = Application::getSubmissionSearchIndex();
        foreach ($importedObjects as $submission) {
            assert($submission instanceof Submission);
            $submissionSearchIndex->submissionMetadataChanged($submission);
            $submissionSearchIndex->submissionFilesChanged($submission);
        }

        $submissionSearchIndex->submissionChangesFinished();

        return $importedObjects;
    }

    /**
     * Parse a submission publication and add it to the submission.
     *
     * @param $n DOMElement
     * @param $submission Submission
     */
    public function parsePublication($n, $submission)
    {
        $importFilter = $this->getImportFilter($n->tagName);
        assert(isset($importFilter)); // There should be a filter

        $submissionFileDoc = new DOMDocument();
        $submissionFileDoc->appendChild($submissionFileDoc->importNode($n, true));
        return $importFilter->execute($submissionFileDoc);
    }

    //
    // Helper functions
    //

    /**
     * Get the import filter for a given element.
     *
     * @param $elementName string Name of XML element
     *
     * @return Filter
     */
    public function getImportFilter($elementName)
    {
        assert(false); // Subclasses should override
    }
}
