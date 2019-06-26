<?php
/**
 * @defgroup controllers_modals_review_linkAction Submission Metadata Link Actions
 */
/**
 * @file controllers/modals/review/ReviewerViewMetadataLinkAction.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewerViewMetadataLinkAction
 * @ingroup controllers_modals_review_linkAction
 *
 * @brief An action to open the submission meta-data modal.
 */

import('lib.pkp.classes.linkAction.LinkAction');

class ReviewerViewMetadataLinkAction extends LinkAction {
	/**
	 * Constructor
	 * @param $request Request
	 * @param $submissionId integer
	 * @param $reviewAssignmentId integer
	 */
	function __construct($request, $submissionId, $reviewAssignmentId) {
		// Instantiate the meta-data modal.
		$dispatcher = $request->getDispatcher();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$modal = new AjaxModal(
				$dispatcher->url($request, ROUTE_COMPONENT, null,
						'modals.submission.ViewSubmissionMetadataHandler',
						'display', null, array('submissionId' => $submissionId, 'reviewAssignmentId' => $reviewAssignmentId)),
				__('reviewer.step1.viewAllDetails'), 'modal_information');
		// Configure the link action.
		parent::__construct('viewMetadata', $modal, __('reviewer.step1.viewAllDetails'));
	}
}
