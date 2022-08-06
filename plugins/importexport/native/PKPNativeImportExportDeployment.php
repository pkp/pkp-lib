<?php

/**
 * @file plugins/importexport/native/PKPNativeImportExportDeployment.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNativeImportExportDeployment
 * @ingroup plugins_importexport_native
 *
 * @brief Base class configuring the native import/export process to an
 * application's specifics.
 */

namespace PKP\plugins\importexport\native;

use PKP\plugins\importexport\PKPImportExportDeployment;
use PKP\submissionFile\SubmissionFile;

class PKPNativeImportExportDeployment extends PKPImportExportDeployment
{
    //
    // Deployment items for subclasses to override
    //
    /**
     * Get the submission node name
     *
     * @return string
     */
    public function getSubmissionNodeName()
    {
        return 'submission';
    }

    /**
     * Get the submissions node name
     *
     * @return string
     */
    public function getSubmissionsNodeName()
    {
        return 'submissions';
    }

    /**
     * Get the namespace URN
     *
     * @return string
     */
    public function getNamespace()
    {
        return 'http://pkp.sfu.ca';
    }

    /**
     * Get the schema filename.
     *
     * @return string
     */
    public function getSchemaFilename()
    {
        return 'pkp-native.xsd';
    }

    /**
     * Get the mapping between stage names in XML and their numeric consts
     *
     * @return array
     */
    public function getStageNameStageIdMapping()
    {
        return [
            'submission' => SubmissionFile::SUBMISSION_FILE_SUBMISSION,
            'note' => SubmissionFile::SUBMISSION_FILE_NOTE,
            'review_file' => SubmissionFile::SUBMISSION_FILE_REVIEW_FILE,
            'review_attachment' => SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT,
            'final' => SubmissionFile::SUBMISSION_FILE_FINAL,
            'copyedit' => SubmissionFile::SUBMISSION_FILE_COPYEDIT,
            'proof' => SubmissionFile::SUBMISSION_FILE_PROOF,
            'production_ready' => SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY,
            'attachment' => SubmissionFile::SUBMISSION_FILE_ATTACHMENT,
            'review_revision' => SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
            'dependent' => SubmissionFile::SUBMISSION_FILE_DEPENDENT,
            'query' => SubmissionFile::SUBMISSION_FILE_QUERY,
        ];
    }
}
