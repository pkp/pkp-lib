<?php

/**
 * @file controllers/grid/files/dependent/DependentFilesGridDataProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DependentFilesGridDataProvider
 * @ingroup controllers_grid_files_dependent
 *
 * @brief Provide access to dependent file data for grids.
 */

namespace PKP\controllers\grid\files\dependent;

use APP\facades\Repo;
use PKP\controllers\api\file\linkAction\AddFileLinkAction;
use PKP\controllers\grid\files\SubmissionFilesGridDataProvider;
use PKP\submissionFile\SubmissionFile;

class DependentFilesGridDataProvider extends SubmissionFilesGridDataProvider
{
    /**
     * The submission file id for the parent file.
     *
     * @var int
     */
    public $_assocId;

    /**
     * Constructor
     *
     * @param int $assocId Association ID
     */
    public function __construct($assocId)
    {
        assert(is_numeric($assocId));
        $this->_assocId = (int) $assocId;
        parent::__construct(SubmissionFile::SUBMISSION_FILE_DEPENDENT);
    }

    /**
     * @copydoc GridDataProvider::loadData()
     */
    public function loadData($filter = [])
    {
        // Retrieve all dependent files for the given file stage and original submission file id (i.e. the main galley/production file)
        $submission = $this->getSubmission();
        $collector = Repo::submissionFile()
            ->getCollector()
            ->filterByAssoc(
                ASSOC_TYPE_SUBMISSION_FILE,
                [$this->getAssocId()]
            )->filterBySubmissionIds([$submission->getId()])
            ->filterByFileStages([$this->getFileStage()])
            ->includeDependentFiles();
        $submissionFilesIterator = Repo::submissionFile()->getMany($collector);
        return $this->prepareSubmissionFileData(iterator_to_array($submissionFilesIterator), $this->_viewableOnly, $filter);
    }

    /**
     * Overridden from SubmissionFilesGridDataProvider - we need to also include the assocType and assocId
     *
     * @copydoc FilesGridDataProvider::getAddFileAction()
     */
    public function getAddFileAction($request)
    {
        $submission = $this->getSubmission();
        return new AddFileLinkAction(
            $request,
            $submission->getId(),
            $this->getStageId(),
            $this->getUploaderRoles(),
            $this->getFileStage(),
            ASSOC_TYPE_SUBMISSION_FILE,
            $this->getAssocId(),
            null,
            null,
            $this->isDependent()
        );
    }

    /**
     * returns the id of the parent submission file for these dependent files.
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->_assocId;
    }

    /**
     * Convenience function to make the argument to the AddFileLinkAction more obvious.
     *
     * @return true
     */
    public function isDependent()
    {
        return true;
    }
}
