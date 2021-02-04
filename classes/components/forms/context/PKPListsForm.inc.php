<?php
/**
 * @file classes/components/form/context/PKPListsForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPListsForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring how a context handles lists of
 *  items in the UI.
 */
namespace PKP\components\forms\context;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldText;

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
