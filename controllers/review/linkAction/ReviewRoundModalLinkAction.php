<?php

/**
 * @file controllers/review/linkAction/ReviewRoundModalLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundModalLinkAction
 *
 * @ingroup controllers_review_linkAction
 *
 * @brief An action to show a modal with the information about a review round.
 */

namespace PKP\controllers\review\linkAction;

use APP\core\Request;
use Exception;
use PKP\core\PKPApplication;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use APP\facades\Repo;

class ReviewRoundModalLinkAction extends LinkAction
{
    /** @var int The round number */
    public int $_round;

    /**
     * Constructor
     *
     * @param Request $request
     * @param int $submissionId The ID of the submission to present link for
     * @param int $reviewRoundId The ID of the review round
     * @param int $reviewRoundNumber The round number to show information about
     * @throws Exception
     */
	public function __construct($request, $submissionId, $reviewRoundId, $reviewRoundNumber)
	{
        $this->_round = $reviewRoundNumber;

		$submission = Repo::submission()->get($submissionId);
		$submissionTitle = $submission->getCurrentPublication()->getLocalizedTitle();
        $router = $request->getRouter();
        $actionArgs = [
            'submissionId' => $submissionId,
            'reviewRoundId' => $reviewRoundId,
            'reviewRoundNumber' => $reviewRoundNumber
        ];

		$ajaxModal = new AjaxModal(
            $router->getDispatcher()->url(
				$request,
                PKPApplication::ROUTE_COMPONENT,
                null,
				'review.ReviewRoundModalHandler',
				'viewRoundInfo',
				null,
                $actionArgs
			),
            __(
                'reviewer.submission.reviewRound.info.modal.title',
                [
                    'reviewRoundNumber' => $reviewRoundNumber,
                    'submissionTitle' => $submissionTitle
                ]
            ),
			'modal_information'
		);

		// Configure the link action.
		parent::__construct('viewRoundInfo', $ajaxModal);
	}

    /**
     * Get the review round number.
     *
     * @return int
     */
    function getRound(): int
    {
        return $this->_round;
    }
}
