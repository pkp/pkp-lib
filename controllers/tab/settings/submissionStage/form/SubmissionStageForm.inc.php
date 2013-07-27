<?php

/**
 * @file controllers/tab/settings/submissionStage/form/SubmissionStageForm.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionStageForm
 * @ingroup controllers_tab_settings_submissionStage_form
 *
 * @brief Form to edit submission stage information.
 */

import('lib.pkp.classes.controllers.tab.settings.form.ContextSettingsForm');

class SubmissionStageForm extends ContextSettingsForm {

	/**
	 * Constructor.
	 */
	function SubmissionStageForm($wizardMode = false) {
		$settings = array(
			'copySubmissionAckPrimaryContact' => 'bool',
			'copySubmissionAckAddress' => 'string'
		);

		$this->addCheck(new FormValidatorEmail($this, 'copySubmissionAckAddress'));

		parent::ContextSettingsForm($settings, 'controllers/tab/settings/submissionStage/form/submissionStageForm.tpl', $wizardMode);
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $params = null) {
		$templateMgr = TemplateManager::getManager($request);

		import('lib.pkp.classes.mail.MailTemplate');
		$mail = new MailTemplate('SUBMISSION_ACK');
		$templateMgr->assign('submissionAckDisabled', !$mail->isEnabled());

		return parent::fetch($request, $params);
	}
}

?>
