<?php
/**
 * @defgroup controllers_review_linkAction Review Link Actions
 */

/**
 * @file controllers/review/linkAction/UnconsiderReviewLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UnconsiderReviewLinkAction
 * @ingroup controllers_review_linkAction
 *
 * @brief An action to allow editors to unconsider a review.
 */

namespace PKP\controllers\review\linkAction;

use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class UnconsiderReviewLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param \PKP\submission\reviewAssignment\ReviewAssignment $reviewAssignment The review assignment
     * to show information about.
     * @param Submission $submission The reviewed submission.
     */
    public function __construct($request, $reviewAssignment, $submission)
    {
        $router = $request->getRouter();
        parent::__construct(
            'unconsiderReview',
            new RemoteActionConfirmationModal(
                $request->getSession(),
                __('editor.review.unconsiderReviewText'),
                __('editor.review.unconsiderReview'),
                $router->url(
                    $request,
                    null,
                    'grid.users.reviewer.ReviewerGridHandler',
                    'unconsiderReview',
                    null,
                    [
                        'submissionId' => $reviewAssignment->getSubmissionId(),
                        'reviewAssignmentId' => $reviewAssignment->getId(),
                        'stageId' => $reviewAssignment->getStageId()
                    ]
                ),
                'modal_information'
            ),
            __('editor.review.revertDecision'),
            'unconsider'
        );
    }
}
