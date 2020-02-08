<?php
/**
 * @file classes/components/form/context/PKPContextForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContextForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for adding and editing a context from the admin area.
 */
namespace PKP\components\forms\context;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldText;
use \PKP\components\forms\FieldRichTextarea;

define('FORM_CONTEXT', 'context');

class PKPContextForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_CONTEXT;

	/** @copydoc FormComponent::$method */
	public $method = 'POST';

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $successMessage string Message to display when form submitted successfully
	 * @param $locales array Supported locales
	 * @param $baseUrl string Base URL for the site
	 * @param $context Context Journal or Press to change settings for
	 */
	public function __construct($action, $successMessage, $locales, $baseUrl, $context) {
		$this->action = $action;
		$this->successMessage = $successMessage;
		$this->locales = $locales;
		$this->method = $context ? 'PUT' : 'POST';

		$this->addField(new FieldText('name', [
				'label' => __('manager.setup.contextTitle'),
				'isRequired' => true,
				'isMultilingual' => true,
				'value' => $context ? $context->getData('name') : null,
			]))
			->addField(new FieldText('acronym', [
				'label' => __('manager.setup.contextInitials'),
				'size' => 'small',
				'isRequired' => true,
				'isMultilingual' => true,
				'groupId' => 'identity',
				'value' => $context ? $context->getData('acronym') : null,
			]))
			->addField(new FieldRichTextarea('description', [
				'label' => __('admin.contexts.contextDescription'),
				'isMultilingual' => true,
				'value' => $context ? $context->getData('description') : null,
			]))
			->addField(new FieldText('urlPath', [
				'label' => __('context.path'),
				'isRequired' => true,
				'value' => $context ? $context->getData('urlPath') : null,
				'prefix' => $baseUrl . '/',
				'size' => 'large',
			]));
	}
}
