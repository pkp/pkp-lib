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
 * @ingroup controllers_modals_review_linkAction
 *
 * @brief An action to open the submission meta-data modal.
 */

namespace PKP\controllers\modals\review;

use PKP\core\PKPApplication;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

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
        // Instantiate the meta-data modal.
        $dispatcher = $request->getDispatcher();
        $modal = new AjaxModal(
            $dispatcher->url(
                $request,
                PKPApplication::ROUTE_COMPONENT,
                null,
                'modals.submission.ViewSubmissionMetadataHandler',
                'display',
                null,
                ['submissionId' => $submissionId, 'reviewAssignmentId' => $reviewAssignmentId]
            ),
            __('reviewer.step1.viewAllDetails'),
            'modal_information'
        );
        // Configure the link action.
        parent::__construct('viewMetadata', $modal, __('reviewer.step1.viewAllDetails'));
    }
}
