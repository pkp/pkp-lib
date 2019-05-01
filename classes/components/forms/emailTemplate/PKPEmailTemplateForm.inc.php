<?php
/**
 * @file classes/components/form/context/PKPEmailTemplateForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailTemplateForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for adding and editing email templates.
 */
namespace PKP\components\forms\emailTemplate;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldHTML;
use \PKP\components\forms\FieldRichTextarea;
use \PKP\components\forms\FieldText;

define('FORM_EMAIL_TEMPLATE', 'editEmailTemplate');

class PKPEmailTemplateForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_EMAIL_TEMPLATE;

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $locales array Supported locales
	 * @param $emailTemplate EmailTemplate
	 */
	public function __construct($action, $locales, $emailTemplate = null) {
		$this->action = $action;
		$this->method = is_null($emailTemplate) ? 'POST' : 'PUT';
		$this->successMessage = is_null($emailTemplate) ? __('manager.emails.addEmail.success') : __('manager.emails.editEmail.success');
		$this->locales = $locales;

		if (is_null($emailTemplate)) {
			$this->addField(new FieldText('key', [
				'label' => __('manager.emails.emailKey'),
				'description' => __('manager.emails.emailKey.description'),
			]));
		} elseif ($emailTemplate->getLocalizedData('description')) {
			$this->addField(new FieldHTML('emailTemplateDescription', [
				'label' => __('about.description'),
				'description' => $emailTemplate->getLocalizedData('description'),
			]));
		}

		$subjectArgs = [
			'label' => __('email.subject'),
			'isMultilingual' => true,
		];
		$bodyArgs = [
			'label' => __('email.body'),
			'size' => 'large',
			'isMultilingual' => true,
		];
		if (!is_null($emailTemplate)) {
			$subjectArgs['value'] = $emailTemplate->getData('subject');
			$bodyArgs['value'] = $emailTemplate->getData('body');
		}

		$this->addField(new FieldText('subject', $subjectArgs))
			->addField(new FieldRichTextarea('body', $bodyArgs));
	}
}
