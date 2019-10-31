<?php
/**
 * @file classes/components/form/context/PKPMastheadForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPMastheadForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a context's masthead details.
 */
namespace PKP\components\forms\context;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldText;
use \PKP\components\forms\FieldRichTextarea;

define('FORM_MASTHEAD', 'masthead');

class PKPMastheadForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_MASTHEAD;

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
		$this->successMessage = __('manager.setup.masthead.success');
		$this->locales = $locales;

		$this->addGroup([
				'id' => 'identity',
				'label' => __('manager.setup.identity'),
			])
			->addField(new FieldText('name', [
				'label' => __('manager.setup.contextTitle'),
				'size' => 'large',
				'isRequired' => true,
				'isMultilingual' => true,
				'groupId' => 'identity',
				'value' => $context->getData('name'),
			]))
			->addField(new FieldText('acronym', [
				'label' => __('manager.setup.contextInitials'),
				'size' => 'small',
				'isRequired' => true,
				'isMultilingual' => true,
				'groupId' => 'identity',
				'value' => $context->getData('acronym'),
			]))
			->addGroup([
				'id' => 'keyInfo',
				'label' => __('manager.setup.keyInfo'),
				'description' => __('manager.setup.keyInfo.description'),
			])
			->addField(new FieldRichTextarea('description', [
				'label' => __('manager.setup.contextSummary'),
				'isMultilingual' => true,
				'groupId' => 'keyInfo',
				'value' => $context->getData('description'),
			]))
			->addField(new FieldRichTextarea('editorialTeam', [
				'label' => __('manager.setup.editorialTeam'),
				'isMultilingual' => true,
				'groupId' => 'keyInfo',
				'toolbar' => 'bold italic superscript subscript | link | image | code',
				'plugins' => 'paste,link,image,code',
				'uploadUrl' => $imageUploadUrl,
				'value' => $context->getData('editorialTeam'),
			]))
			->addGroup([
				'id' => 'about',
				'label' => __('common.description'),
				'description' => __('manager.setup.contextAbout.description'),
			])
			->addField(new FieldRichTextarea('about', [
				'label' => __('manager.setup.contextAbout'),
				'isMultilingual' => true,
				'size' => 'large',
				'groupId' => 'about',
				'toolbar' => 'bold italic superscript subscript | link | image | code',
				'plugins' => 'paste,link,image,code',
				'uploadUrl' => $imageUploadUrl,
				'value' => $context->getData('about'),
			]));
	}
}
