<?php
/**
 * @file controllers/informationCenter/linkAction/ReadSignoffHistoryLinkAction.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReadSignoffHistoryLinkAction
 * @ingroup controllers_informationCenter_linkAction
 *
 * @brief An action to open the signoff history modal.
 */

import('lib.pkp.classes.linkAction.LinkAction');

class ReadSignoffHistoryLinkAction extends LinkAction {

	/**
	 * Constructor
	 * @param $request Request
	 * @param $signoffId int The signoff id that will
	 * be used to get notes from.
	 * @param $submissionId int The signoff submission id.
	 * @param $stageId int The signoff stage id.
	 */
	function ReadSignoffHistoryLinkAction($request, $signoffId, $submissionId, $stageId) {
		// Instantiate the redirect action request.
		$dispatcher = $request->getDispatcher();
		import('lib.pkp.classes.linkAction.request.AjaxModal');

		$actionArgs = array(
			'signoffId' => $signoffId,
			'submissionId' => $submissionId,
			'stageId' => $stageId
		);
		parent::LinkAction(
			'history',
			new AjaxModal(
				$dispatcher->url($request, ROUTE_COMPONENT, null, 'informationCenter.SignoffInformationCenterHandler',
					'viewSignoffHistory', null, $actionArgs),
				__('submission.history'),
				'modal_information',
				true
			),
			__('submission.history'),
			'more_info'
		);
	}
}

?>
