<?php
/**
 * @file controllers/grid/files/attachment/ReviewerReviewAttachmentGridDataProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerReviewAttachmentGridDataProvider
 * @ingroup controllers_grid_files_attachment
 *
 * @brief Provide the reviewers access to their own review attachments data for grids.
 */

namespace PKP\controllers\grid\files\attachment;

use APP\facades\Repo;
use PKP\controllers\api\file\linkAction\AddFileLinkAction;
use PKP\controllers\grid\files\SubmissionFilesGridDataProvider;
use PKP\db\DAORegistry;
use PKP\security\authorization\internal\ReviewAssignmentRequiredPolicy;
use PKP\security\authorization\ReviewStageAccessPolicy;
use PKP\submissionFile\SubmissionFile;

class ReviewerReviewAttachmentGridDataProvider extends SubmissionFilesGridDataProvider
{
    /** @var int */
    public $_reviewId;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT);
    }


    //
    // Implement template methods from GridDataProvider
    //
    /**
     * @copydoc GridDataProvider::getAuthorizationPolicy()
     */
    public function getAuthorizationPolicy($request, $args, $roleAssignments)
    {
        // Need to use the reviewId because this grid can either be
        // viewed by the reviewer (in which case, we could do a
        // $request->getUser()->getId() or by the editor when reading
        // the review. The following covers both cases...
        $assocType = (int) $request->getUserVar('assocType');
        $assocId = (int) $request->getUserVar('assocId');
        if ($assocType && $assocId) {
            // Viewing from a Reviewer perspective.
            assert($assocType == ASSOC_TYPE_REVIEW_ASSIGNMENT);

            $this->setUploaderRoles($roleAssignments);

            $authorizationPolicy = new ReviewStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $request->getUserVar('stageId'));
            $paramName = 'assocId';
        } else {
            // Viewing from a context role perspective.
            $authorizationPolicy = parent::getAuthorizationPolicy($request, $args, $roleAssignments);
            $paramName = 'reviewId';
        }

        $authorizationPolicy->addPolicy(new ReviewAssignmentRequiredPolicy($request, $args, $paramName));

        return $authorizationPolicy;
    }

    /**
     * @copydoc GridDataProvider::getRequestArgs()
     */
    public function getRequestArgs()
    {
        return array_merge(
            parent::getRequestArgs(),
            [
                'assocType' => ASSOC_TYPE_REVIEW_ASSIGNMENT,
                'assocId' => $this->_getReviewId()
            ]
        );
    }

    /**
     * @copydoc GridDataProvider::loadData()
     */
    public function loadData($filter = [])
    {
        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterByAssoc(
                ASSOC_TYPE_REVIEW_ASSIGNMENT,
                [$this->_getReviewId()]
            )->filterBySubmissionIds([$this->getSubmission()->getId()])
            ->getMany()
            ->toArray();
        return $this->prepareSubmissionFileData($submissionFiles, false, $filter);
    }

    //
    // Overridden public methods from FilesGridDataProvider
    //
    /**
     * @copydoc FilesGridDataProvider::getAddFileAction()
     */
    public function getAddFileAction($request)
    {
        $submission = $this->getSubmission();

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignment = $reviewAssignmentDao->getById($this->_getReviewId());

        return new AddFileLinkAction(
            $request,
            $submission->getId(),
            $this->getStageId(),
            $this->getUploaderRoles(),
            $this->getFileStage(),
            ASSOC_TYPE_REVIEW_ASSIGNMENT,
            $this->_getReviewId(),
            $reviewAssignment->getReviewRoundId()
        );
    }
    //
    // Private helper methods
    //
    /**
     * Get the review id.
     *
     * @return int
     */
    public function _getReviewId()
    {
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        return $reviewAssignment->getId();
    }
}
