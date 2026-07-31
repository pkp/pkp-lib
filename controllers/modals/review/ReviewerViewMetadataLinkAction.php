<?php
/**
 * @defgroup controllers_modals_review_linkAction Submission Metadata Link Actions
 */
/**
 * @file controllers/modals/review/ReviewerViewMetadataLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerViewMetadataLinkAction
 *
 * @ingroup controllers_modals_review_linkAction
 *
 * @brief An action to open the submission metadata modal.
 */

namespace PKP\controllers\modals\review;

use APP\core\Request;
use APP\facades\Repo;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\VueModal;

class ReviewerViewMetadataLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param int $submissionId
     * @param int $reviewAssignmentId
     */
    public function __construct($request, $submissionId, $reviewAssignmentId)
    {
        $submission = Repo::submission()->get($submissionId);

        parent::__construct(
            'viewMetadata',
            new VueModal(
                'ReviewerSubmissionDetailsModal',
                [
                    'submissionId' => (int) $submissionId,
                    'publicationId' => (int) $submission->getCurrentPublication()->getId(),
                ]
            ),
            __('reviewer.step1.viewAllDetails')
        );
    }
}
