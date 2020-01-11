<?php
/**
 * @file classes/components/form/context/PKPPrivacyForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPrivacyForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a context's privacy statement.
 */
namespace PKP\components\forms\context;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldRichTextarea;

define('FORM_PRIVACY', 'privacy');

class PKPPrivacyForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_PRIVACY;

	/** @copydoc FormComponent::$method */
	public $method = 'PUT';

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $locales array Supported locales
	 * @param $context Context Journal or Press to change settings for
	 * @param $imageUploadUrl string The API endpoint for images uploaded through the rich text field
	 */
	public function __construct($action, $locales, $context, $imageUploadUrl) {
		$this->action = $action;
		$this->successMessage = __('manager.setup.privacyStatement.success');
		$this->locales = $locales;

		$this->addField(new FieldRichTextArea('privacyStatement', [
				'label' => __('manager.setup.privacyStatement'),
				'description' => __('manager.setup.privacyStatement.description'),
				'isMultilingual' => true,
				'value' => $context->getData('privacyStatement'),
				'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
				'plugins' => 'paste,link,lists,image,code',
				'uploadUrl' => $imageUploadUrl,
			]));
	}
}
