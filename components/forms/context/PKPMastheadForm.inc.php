<?php
/**
 * @file controllers/form/context/PKPMastheadForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPMastheadForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a context's masthead details.
 */
import('lib.pkp.components.forms.FormComponent');

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
	 */
	public function __construct($action, $locales, $context) {
		$this->action = $action;
		$this->successMessage = __('manager.setup.masthead.success');
		$this->locales = $locales;

		$this->addGroup([
				'id' => 'identity',
				'label' => __('manager.setup.identity'),
			])
			->addField(new FieldText('name', [
				'label' => __('manager.setup.contextName'),
				'size' => 'large',
				'isRequired' => true,
				'isMultilingual' => true,
				'groupId' => 'identity',
				'value' => $context->getData('name'),
			]))
			->addField(new FieldText('acronym', [
				'label' => __('manager.setup.journalInitials'),
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
				'label' => __('manager.setup.journalSummary'),
				'isMultilingual' => true,
				'groupId' => 'keyInfo',
				'value' => $context->getData('description'),
			]))
			->addField(new FieldRichTextarea('editorialTeam', [
				'label' => __('manager.setup.editorialTeam'),
				'isMultilingual' => true,
				'groupId' => 'keyInfo',
				'value' => $context->getData('editorialTeam'),
			]))
			->addGroup([
				'id' => 'about',
				'label' => __('common.description'),
				'description' => __('manager.setup.journalAbout.description'),
			])
			->addField(new FieldRichTextarea('about', [
				'label' => __('manager.setup.journalAbout'),
				'isMultilingual' => true,
				'size' => 'large',
				'groupId' => 'about',
				'value' => $context->getData('about'),
			]));
	}
}
