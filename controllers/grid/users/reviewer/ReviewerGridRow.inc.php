<?php

/**
 * @file controllers/grid/users/reviewer/ReviewerGridRow.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewerGridRow
 * @ingroup controllers_grid_users_reviewer
 *
 * @brief Reviewer grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class ReviewerGridRow extends GridRow {

	/** @var boolean Is the current user assigned as an author to this submission */
	public $_isCurrentUserAssignedAuthor;

	/**
	 * Constructor
	 * @param $isCurrentUserAssignedAuthor boolean Is the current user assigned as an
	 *  author to this submission?
	 */
	public function __construct($isCurrentUserAssignedAuthor) {
		parent::__construct();
		$this->_isCurrentUserAssignedAuthor = $isCurrentUserAssignedAuthor;
	}

	//
	// Overridden methods from GridRow
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request, $template = null) {
		parent::initialize($request, $template);

		// Retrieve the submission id from the request
		// These parameters need not be validated as we're just
		// passing them along to another request, where they will be
		// checked before they're used.
		$submissionId = (int) $request->getUserVar('submissionId');
		$stageId = (int) $request->getUserVar('stageId');
		$round = (int) $request->getUserVar('round');

		// Authors can't perform any actions on blind reviews
		$reviewAssignment = $this->getData();
		$isAuthorBlind = in_array($reviewAssignment->getReviewMethod(), array(SUBMISSION_REVIEW_METHOD_BLIND, SUBMISSION_REVIEW_METHOD_DOUBLEBLIND));
		if ($this->_isCurrentUserAssignedAuthor && $isAuthorBlind) {
			return;
		}

		// Is this a new row or an existing row?
		$rowId = $this->getId();
		if (!empty($rowId) && is_numeric($rowId)) {
			// Only add row actions if this is an existing row
			$router = $request->getRouter();
			$actionArgs = array(
				'submissionId' => $submissionId,
				'reviewAssignmentId' => $rowId,
				'stageId' => $stageId,
				'round' => $round
			);

			// read or upload a review
			$submissionDao = Application::getSubmissionDAO();
			$submission = $submissionDao->getById($submissionId);
			$this->addAction(
				new LinkAction(
					'readReview',
					new AjaxModal(
						$router->url($request, null, null, 'readReview', null, $actionArgs),
						__('editor.review.reviewDetails') . ': ' . $submission->getLocalizedTitle(),
						'modal_information'
					),
					__('editor.review.reviewDetails'),
					'more_info'
				)
			);

			$this->addAction(
				new LinkAction(
					'email',
					new AjaxModal(
						$router->url($request, null, null, 'sendEmail', null, $actionArgs),
						__('editor.review.emailReviewer'),
						'modal_email'
					),
					__('editor.review.emailReviewer'),
					'notify'
				)
			);

			if (!$this->_isCurrentUserAssignedAuthor) {
				$this->addAction(
					new LinkAction(
						'manageAccess',
						new AjaxModal(
							$router->url($request, null, null, 'editReview', null, $actionArgs),
							__('editor.submissionReview.editReview'),
							'modal_add_file'
						),
						__('common.edit'),
						'edit'
					)
				);

				$reviewAssignment = $this->getData();
				// Only assign this action if the reviewer has not acknowledged yet.
				if (!$reviewAssignment->getDateConfirmed()) {
					$this->addAction(
						new LinkAction(
							'unassignReviewer',
							new AjaxModal(
								$router->url($request, null, null, 'unassignReviewer', null, $actionArgs),
								__('editor.review.unassignReviewer'),
								'modal_delete'
							),
						__('editor.review.unassignReviewer'),
						'delete'
						)
					);
				}
			}

			$this->addAction(
				new LinkAction(
					'history',
					new AjaxModal(
						$router->url($request, null, null, 'reviewHistory', null, $actionArgs),
						__('submission.history'),
						'modal_information'
					),
					__('submission.history'),
					'more_info'
				)
			);

			// Add gossip action when appropriate
			import('classes.core.ServicesContainer');
			$canCurrentUserGossip = ServicesContainer::instance()
				->get('user')
				->canCurrentUserGossip($reviewAssignment->getReviewerId());
			if ($canCurrentUserGossip && !$isAuthorBlind) {
				$this->addAction(
					new LinkAction(
						'gossip',
						new AjaxModal(
							$router->url($request, null, null, 'gossip', null, $actionArgs),
							__('user.gossip'),
							'modal_information'
						),
						__('user.gossip'),
						'more_info'
					)
				);
			}
		}
	}
}

?>
