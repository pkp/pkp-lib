<?php

/**
 * @defgroup controllers_review Review Handlers
 */

/**
 * @file controllers/review/ReviewRoundModalHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Copyright (c) 2021 Université Laval
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundModalHandler
 * @ingroup controllers_review
 *
 * @brief Reviewer review round info handler.
 */

namespace PKP\controllers\review;

use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use Exception;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\log\SubmissionEmailLogEntry;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\db\DAORegistry;
use PKP\security\Role;

class ReviewRoundModalHandler extends Handler
{
	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct();

		$this->addRoleAssignment(
			[Role::ROLE_ID_REVIEWER],
			['viewRoundInfo', 'closeModal']
		);
	}

	//
	// Implement template methods from PKPHandler.
	//

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments): bool
    {
		$this->addPolicy(new RoleBasedHandlerOperationPolicy(
			$request,
			[Role::ROLE_ID_REVIEWER],
			['viewRoundInfo', 'close']
		));

		return parent::authorize($request, $args, $roleAssignments);
	}

	//
	// Public operations
	//

    /**
     * Display the review round info modal.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     * @throws Exception
     */
	function viewRoundInfo($args, $request)
	{
		$this->setupTemplate($request);

		$submission = Repo::submission()->get($args['submissionId']);
        $submissionId = $submission->getId();
		$reviewerId = $request->getUser()->getId();

        $reviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterByReviewerIds([$reviewerId])
            ->getMany();
		$declinedReviewAssignments = array();
		foreach ($reviewAssignments as $submissionReviewAssignment) {
			if ($submissionReviewAssignment->getDeclined() and $submissionId == $submissionReviewAssignment->getSubmissionId()) {
				$declinedReviewAssignments[] = $submissionReviewAssignment;
			}
		}

        $reviewAssignment = Repo::reviewAssignment()->getCollector()
            ->filterByReviewRoundIds([$args['reviewRoundId']])
            ->filterByReviewerIds([$reviewerId])
            ->filterByContextIds([$request->getContext()->getId()])
            ->getMany()
            ->first();
		$submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
		$reviewComments = $submissionCommentDao->getReviewerCommentsByReviewerId($submissionId, $reviewerId, $reviewAssignment->getId());

        $reviewRoundNumber = $args['reviewRoundNumber'];
		$submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
		$emailLogs = $submissionEmailLogDao
            ->getBySenderId($submissionId, SubmissionEmailLogEntry::SUBMISSION_EMAIL_REVIEW_DECLINE, $reviewerId)
            ->toArray();
		$declineEmail = null;
        $i = 0;
		foreach ($declinedReviewAssignments as $declinedReviewAssignment) {
			if (isset($emailLogs[$i]) && $reviewRoundNumber == $declinedReviewAssignment->getRound()) {
				$declineEmail = $emailLogs[$i];
			}
			$i++;
		}

		$displayFilesGrid = true;
        $lastReviewAssignment = Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewerIds([$reviewerId])
            ->filterByLastReviewRound(true)
            ->getMany()
            ->first();
		if($lastReviewAssignment->getDeclined() == 1) {
			$displayFilesGrid = false;
		}

        $templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'submission' => $submission,
			'reviewAssignment' => $reviewAssignment,
			'reviewRoundNumber' => $reviewRoundNumber,
			'reviewRoundId' => $args['reviewRoundId'],
			'reviewComments' => $reviewComments,
			'declineEmail' => $declineEmail,
			'displayFilesGrid' => $displayFilesGrid
		]);

		return $templateMgr->fetchJson('controllers/modals/reviewRound/reviewRound.tpl');
	}
}
