<?php

/**
 * @file controllers/tab/settings/reviewStage/form/PKPReviewStageForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewStageForm
 * @ingroup controllers_tab_settings_reviewStage_form
 *
 * @brief Form to edit review stage settings.
 */

import('lib.pkp.classes.controllers.tab.settings.form.ContextSettingsForm');

class PKPReviewStageForm extends ContextSettingsForm {

	/**
	 * Constructor.
	 */
	function PKPReviewStageForm($wizardMode = false, $settings = array(), $template = 'controllers/tab/settings/reviewStage/form/reviewStageForm.tpl') {
		parent::ContextSettingsForm(
			array_merge(
				$settings,
				array(
					'reviewGuidelines' => 'string',
					'competingInterests' => 'string',
					'numWeeksPerResponse' => 'int',
					'numWeeksPerReview' => 'int',
					'numDaysBeforeInviteReminder' => 'int',
					'numDaysBeforeSubmitReminder' => 'int',
					'rateReviewerOnQuality' => 'bool',
					'showEnsuringLink' => 'bool',
					'reviewerCompetingInterestsRequired' => 'bool',
				)
			),
			$template,
			$wizardMode
		);
	}


	//
	// Implement template methods from Form.
	//
	/**
	 * @copydoc Form::getLocaleFieldNames()
	 */
	function getLocaleFieldNames() {
		return array('reviewGuidelines', 'competingInterests');
	}

	/**
	 * @copydoc ContextSettingsForm::fetch()
	 */
	function fetch($request) {
		$params = array();

		// Ensuring blind review link.
		import('lib.pkp.classes.linkAction.request.ConfirmationModal');
		import('lib.pkp.classes.linkAction.LinkAction');
		$params['ensuringLink'] = new LinkAction(
			'showReviewPolicy',
			new ConfirmationModal(
				__('review.blindPeerReview'),
				__('review.ensuringBlindReview'), 'modal_information', null, null, true, MODAL_WIDTH_DEFAULT),
			__('review.ensuringBlindReview')
		);

		$params['scheduledTasksDisabled'] = (Config::getVar('general', 'scheduled_tasks')) ? false : true;

		$templateMgr = TemplateManager::getManager($request);

		$templateMgr->assign('numDaysBeforeInviteReminderValues', range(3, 10));
		$templateMgr->assign('numDaysBeforeSubmitReminderValues', range(0, 10));

		return parent::fetch($request, $params);
	}
}

?>
