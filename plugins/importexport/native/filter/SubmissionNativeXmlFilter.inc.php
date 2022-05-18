<?php

/**
 * @file plugins/importexport/native/filter/SubmissionNativeXmlFilter.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionNativeXmlFilter
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a set of submissions to a Native XML document
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

use APP\facades\Repo;
use PKP\submissionFile\SubmissionFile;

class SubmissionNativeXmlFilter extends NativeExportFilter
{
    public $_includeSubmissionsNode;

    /**
     * Constructor
     *
     * @param FilterGroup $filterGroup
     */
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML submission export');
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
        return 'lib.pkp.plugins.importexport.native.filter.SubmissionNativeXmlFilter';
    }


    //
    // Implement template methods from Filter
    //
    /**
     * @see Filter::process()
     *
     * @param array $submissions Array of submissions
     *
     * @return DOMDocument
     */
    public function &process(&$submissions)
    {
        // Create the XML document
        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();

        if (count($submissions) == 1 && !$this->getIncludeSubmissionsNode()) {
            // Only one submission specified; create root node
            $rootNode = $this->createSubmissionNode($doc, $submissions[0]);
        } else {
            // Multiple submissions; wrap in a <submissions> element
            $rootNode = $doc->createElementNS($deployment->getNamespace(), $deployment->getSubmissionsNodeName());
            foreach ($submissions as $submission) {
                $rootNode->appendChild($this->createSubmissionNode($doc, $submission));
            }
        }
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    //
    // Submission conversion functions
    //
    /**
     * Create and return a submission node.
     *
     * @param DOMDocument $doc
     * @param Submission $submission
     *
     * @return DOMElement
     */
    public function createSubmissionNode($doc, $submission)
    {
        // Create the root node and attributes
        $deployment = $this->getDeployment();
        $deployment->setSubmission($submission);
        $submissionNode = $doc->createElementNS($deployment->getNamespace(), $deployment->getSubmissionNodeName());

        $submissionNode->setAttribute('locale', $submission->getData('locale'));
        $submissionNode->setAttribute('date_submitted', date('Y-m-d', strtotime($submission->getData('dateSubmitted'))));
        $submissionNode->setAttribute('status', $submission->getData('status'));
        $submissionNode->setAttribute('submission_progress', $submission->getData('submissionProgress'));
        $submissionNode->setAttribute('current_publication_id', $submission->getData('currentPublicationId'));

        $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO'); /** @var WorkflowStageDAO $workflowStageDao */
        $submissionNode->setAttribute('stage', WorkflowStageDAO::getPathFromId($submission->getData('stageId')));

        $this->addIdentifiers($doc, $submissionNode, $submission);
        $this->addFiles($doc, $submissionNode, $submission);
        $this->addPublications($doc, $submissionNode, $submission);

        return $submissionNode;
    }

    /**
     * Create and add identifier nodes to a submission node.
     *
     * @param DOMDocument $doc
     * @param DOMElement $submissionNode
     * @param Submission $submission
     */
    public function addIdentifiers($doc, $submissionNode, $submission)
    {
        $deployment = $this->getDeployment();

        // Add internal ID
        $submissionNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'id', $submission->getId()));
        $node->setAttribute('type', 'internal');
        $node->setAttribute('advice', 'ignore');
    }

    /**
     * Add the submission files to its DOM element.
     *
     * @param DOMDocument $doc
     * @param DOMElement $submissionNode
     * @param Submission $submission
     */
    public function addFiles($doc, $submissionNode, $submission)
    {
        $collector = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->includeDependentFiles();

        $submissionFiles = Repo::submissionFile()->getMany($collector);
        foreach ($submissionFiles as $submissionFile) {
            // Skip files attached to objects that are not included in the export,
            // such as files uploaded to discussions and files uploaded by reviewers
            if (in_array($submissionFile->getData('fileStage'), [SubmissionFile::SUBMISSION_FILE_QUERY, SubmissionFile::SUBMISSION_FILE_NOTE, SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT])) {
                $this->getDeployment()->addWarning(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.native.error.submissionFileSkipped', ['id' => $submissionFile->getId()]));
                continue;
            }
            $currentFilter = PKPImportExportFilter::getFilter('SubmissionFile=>native-xml', $this->getDeployment(), $this->opts);
            $submissionFileDoc = $currentFilter->execute($submissionFile, true);

            $clone = $doc->importNode($submissionFileDoc->documentElement, true);
            $submissionNode->appendChild($clone);
        }
    }

    /**
     * Add the submission files to its DOM element.
     *
     * @param DOMDocument $doc
     * @param DOMElement $submissionNode
     * @param Submission $submission
     */
    public function addPublications($doc, $submissionNode, $submission)
    {
        $currentFilter = PKPImportExportFilter::getFilter('publication=>native-xml', $this->getDeployment());

        $publications = $submission->getData('publications');
        foreach ($publications as $publication) {
            $publicationDoc = $currentFilter->execute($publication);

            if ($publicationDoc && $publicationDoc->documentElement instanceof DOMElement) {
                $clone = $doc->importNode($publicationDoc->documentElement, true);
                $submissionNode->appendChild($clone);
            } else {
                $deployment = $this->getDeployment();
                $deployment->addError(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.publication.exportFailed'));

                throw new Exception(__('plugins.importexport.publication.exportFailed'));
            }
        }
    }


    //
    // Abstract methods for subclasses to implement
    //

    /**
     * Sets a flag to always include the <submissions> node, even if there
     * may only be one submission.
     *
     * @param bool $includeSubmissionsNode
     */
    public function setIncludeSubmissionsNode($includeSubmissionsNode)
    {
        $this->_includeSubmissionsNode = $includeSubmissionsNode;
    }

    /**
     * Returnes whether to always include the <submissions> node, even if there
     * may only be one submission.
     *
     * @return bool $includeSubmissionsNode
     */
    public function getIncludeSubmissionsNode()
    {
        return $this->_includeSubmissionsNode;
    }
}
