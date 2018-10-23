<?php
/**
 * @file controllers/form/context/PKPListsForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPListsForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring how a context handles lists of
 *  items in the UI.
 */
import('lib.pkp.components.forms.FormComponent');

define('FORM_LISTS', 'lists');

class PKPListsForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_LISTS;

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
		$this->successMessage = __('manager.setup.lists.success');
		$this->locales = $locales;

		$this->addField(new FieldText('itemsPerPage', [
				'label' => __('common.itemsPerPage'),
				'description' => __('manager.setup.itemsPerPage.description'),
				'isRequired' => true,
				'value' => $context->getData('itemsPerPage'),
				'size' => 'small',
			]))
			->addField(new FieldText('numPageLinks', [
				'label' => __('manager.setup.numPageLinks'),
				'description' => __('manager.setup.numPageLinks.description'),
				'isRequired' => true,
				'value' => $context->getData('numPageLinks'),
				'size' => 'small',
			]));
	}
}
