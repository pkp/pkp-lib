<?php
/**
 * @file classes/workflow/linkAction/ExpediteSubmissionLinkAction.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ExpediteSubmissionLinkAction
 * @ingroup classes_workflow_linkAction
 *
 * @brief An action to permit expedited publication of a submission.
 */

import('lib.pkp.classes.linkAction.LinkAction');
import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');

class ExpediteSubmissionLinkAction extends LinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $submissionId integer The submission to expedite.
	 */
	function ExpediteSubmissionLinkAction($request, $submissionId) {
		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		parent::LinkAction(
			'expedite',
			new AjaxModal(
	                        $dispatcher->url(
	                                $request, ROUTE_PAGE, null,
	                                'workflow',
	                                'expedite', (int) $submissionId
	                        ),
	                        __('submission.submit.expediteSubmission'),
	                        'modal_information'
	                ),
			__('submission.submit.expediteSubmission')
		);
	}

	/**
	 * Determines whether or not this user can expedite this submission.
	 * @param User $user
	 * @param Context $context
	 */
	function canExpedite($user, $context) {
		$userGroupAssignmentDao = DAORegistry::getDAO('UserGroupAssignmentDAO');
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroupAssignments = $userGroupAssignmentDao->getByUserId($user->getId(), $context->getId());
		if (!$userGroupAssignments->wasEmpty()) {
			while ($userGroupAssignment = $userGroupAssignments->next()) {
				$userGroup = $userGroupDao->getById($userGroupAssignment->getUserGroupId());
				if (in_array($userGroup->getRoleId(), array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR))) {
					return true;
				}
			}
		}

		return false;
	}
}

?>
