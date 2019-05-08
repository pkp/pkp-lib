<?php
/**
 * @file classes/components/form/context/PKPInformationForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPInformationForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring the information fields for a
 *  context (eg - info for readers, authors and librarians).
 */
namespace PKP\components\forms\context;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldRichTextarea;

define('FORM_INFORMATION', 'information');

class PKPInformationForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_INFORMATION;

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
		$this->successMessage = __('manager.setup.information.success');
		$this->locales = $locales;

		$this->addGroup([
				'id' => 'descriptions',
				'label' => __('manager.setup.information.descriptionTitle'),
				'description' => __('manager.setup.information.description'),
			])
			->addField(new FieldRichTextarea('readerInformation', [
				'label' => __('manager.setup.information.forReaders'),
				'isMultilingual' => true,
				'groupId' => 'descriptions',
				'value' => $context->getData('readerInformation'),
			]))
			->addField(new FieldRichTextarea('authorInformation', [
				'label' => __('manager.setup.information.forAuthors'),
				'isMultilingual' => true,
				'groupId' => 'descriptions',
				'value' => $context->getData('authorInformation'),
			]))
			->addField(new FieldRichTextarea('librarianInformation', [
				'label' => __('manager.setup.information.forLibrarians'),
				'isMultilingual' => true,
				'groupId' => 'descriptions',
				'value' => $context->getData('librarianInformation'),
			]));
	}
}
