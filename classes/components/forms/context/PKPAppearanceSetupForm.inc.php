<?php
/**
 * @file classes/components/form/context/PKPAppearanceSetupForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAppearanceSetupForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for general website appearance setup, such as uploading
 *  a logo.
 */
namespace PKP\components\forms\context;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldOptions;
use \PKP\components\forms\FieldRichTextarea;
use \PKP\components\forms\FieldUploadImage;

define('FORM_APPEARANCE_SETUP', 'appearanceSetup');

class PKPAppearanceSetupForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_APPEARANCE_SETUP;

	/** @copydoc FormComponent::$method */
	public $method = 'PUT';

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $locales array Supported locales
	 * @param $context Context Journal or Press to change settings for
	 * @param $baseUrl string Site's base URL. Used for image previews.
	 * @param $temporaryFileApiUrl string URL to upload files to
	 */
	public function __construct($action, $locales, $context, $baseUrl, $temporaryFileApiUrl) {
		$this->action = $action;
		$this->successMessage = __('manager.setup.appearance.success');
		$this->locales = $locales;

		$sidebarOptions = [];
		$plugins = \PluginRegistry::loadCategory('blocks', true);
		foreach ($plugins as $pluginName => $plugin) {
			$sidebarOptions[] = [
				'value' => $pluginName,
				'label' => $plugin->getDisplayName(),
			];
		}

		$this->addField(new FieldUploadImage('pageHeaderLogoImage', [
				'label' => __('manager.setup.logo'),
				'value' => $context->getData('pageHeaderLogoImage'),
				'isMultilingual' => true,
				'baseUrl' => $baseUrl,
				'options' => [
					'url' => $temporaryFileApiUrl,
				],
			]))
			->addField(new FieldUploadImage('homepageImage', [
				'label' => __('manager.setup.homepageImage'),
				'tooltip' => __('manager.setup.homepageImage.description'),
				'value' => $context->getData('homepageImage'),
				'isMultilingual' => true,
				'baseUrl' => $baseUrl,
				'options' => [
					'url' => $temporaryFileApiUrl,
				],
			]))
			->addField(new FieldRichTextarea('pageFooter', [
				'label' => __('manager.setup.pageFooter'),
				'tooltip' => __('manager.setup.pageFooter.description'),
				'isMultilingual' => true,
				'value' => $context->getData('pageFooter'),
			]))
			->addField(new FieldOptions('sidebar', [
				'label' => __('manager.setup.layout.sidebar'),
				'isOrderable' => true,
				'value' => (array) $context->getData('sidebar'),
				'options' => $sidebarOptions,
			]));

	}

}
