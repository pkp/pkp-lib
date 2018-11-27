<?php

/**
 * @file controllers/tab/settings/reviewStage/form/PKPReviewStageForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
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
	function __construct($wizardMode = false, $settings = array(), $template = 'controllers/tab/settings/reviewStage/form/reviewStageForm.tpl') {
		parent::__construct(
			array_merge(
				$settings,
				array(
					'reviewGuidelines' => 'string',
					'competingInterests' => 'string',
					'numWeeksPerResponse' => 'int',
					'numWeeksPerReview' => 'int',
					'numDaysBeforeInviteReminder' => 'int',
					'numDaysBeforeSubmitReminder' => 'int',
					'showEnsuringLink' => 'bool',
					'reviewerCompetingInterestsRequired' => 'bool',
					'defaultReviewMode' => 'int',
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
	function fetch($request, $template = null, $display = false, $params = array()) {
		$templateMgr = TemplateManager::getManager($request);
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$templateMgr->assign(array(
			'numDaysBeforeInviteReminderValues' => array_combine(range(1, 10), range(1, 10)),
			'numDaysBeforeSubmitReminderValues' => array_combine(range(1, 10), range(1, 10)),
			'reviewMethodOptions' => $reviewAssignmentDao->getReviewMethodsTranslationKeys(),
		));

		import('lib.pkp.classes.linkAction.request.ConfirmationModal');
		import('lib.pkp.classes.linkAction.LinkAction');
		return parent::fetch($request, $template, $display, array_merge($params, array(
			'ensuringLink' => new LinkAction(
				'showReviewPolicy',
				new ConfirmationModal(
					__('review.blindPeerReview'),
					__('review.ensuringBlindReview'), 'modal_information', null, null, true, MODAL_WIDTH_DEFAULT),
				__('manager.setup.reviewOptions.showBlindReviewLink')
			),
			'scheduledTasksDisabled' => Config::getVar('general', 'scheduled_tasks') ? false : true,
		)));
	}
}


