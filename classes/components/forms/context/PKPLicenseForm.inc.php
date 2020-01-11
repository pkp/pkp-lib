<?php
/**
 * @file classes/components/form/context/PKPLicenseForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPLicenseForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a context's default licensing details.
 */
namespace PKP\components\forms\context;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldRadioInput;
use \PKP\components\forms\FieldRichTextarea;

define('FORM_LICENSE', 'license');

class PKPLicenseForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_LICENSE;

	/** @copydoc FormComponent::$method */
	public $method = 'PUT';

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $locales array Supported locales
	 * @param $context Context Journal or Press to change settings for
	 */
	public function __construct($action, $locales, $context) {
		$this->action = $action;
		$this->successMessage = __('manager.distribution.license.success');
		$this->locales = $locales;

		$licenseOptions = \Application::getCCLicenseOptions();
		$licenseUrlOptions = [];
		foreach ($licenseOptions as $url => $label) {
			$licenseUrlOptions[] = [
				'value' => $url,
				'label' => __($label),
			];
		}
		$licenseUrlOptions[] = [
			'value' => 'other',
			'label' => __('manager.distribution.license.other'),
			'isInput' => true,
		];

		$copyrightHolder = $context->getData('copyrightHolderType');
		if (!empty($copyrightHolder) && !in_array($copyrightHolder, ['author', 'context'])) {
			$copyrightHolder = $context->getData('copyrightHolderOther');
		}

		$this->addField(new FieldRadioInput('copyrightHolder', [
				'label' => __('submission.copyrightHolder'),
				'helpTopic' => 'settings',
				'helpSection' => 'copyright-v-license',
				'type' => 'radio',
				'options' => [
					['value' => 'author', 'label' => __('user.role.author')],
					['value' => 'context', 'label' => __('context.context')],
					['value' => 'other', 'isInput' => true],
				],
				'value' => $copyrightHolder,
			]))
			->addField(new FieldRadioInput('licenseUrl', [
				'label' => __('manager.distribution.license'),
				'helpTopic' => 'settings',
				'helpSection' => 'copyright-v-license',
				'type' => 'radio',
				'options' => $licenseUrlOptions,
				'value' => $context->getData('licenseUrl'),
			]))
			->addField(new FieldRichTextarea('licenseTerms', [
				'label' => __('manager.distribution.licenseTerms'),
				'tooltip' => __('manager.distribution.licenseTerms.description'),
				'isMultilingual' => true,
				'value' => $context->getData('licenseTerms'),
				'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
				'plugins' => 'paste,link,lists',
			]));
	}
}
