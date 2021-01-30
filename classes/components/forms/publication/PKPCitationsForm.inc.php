<?php
/**
 * @file classes/components/form/publication/PKPCitationsForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCitationsForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's citations
 */
namespace PKP\components\forms\publication;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldTextarea;

define('FORM_CITATIONS', 'citations');

class PKPCitationsForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_CITATIONS;

	/** @copydoc FormComponent::$method */
	public $method = 'PUT';

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $publication Publication The publication to change settings for
	 */
	public function __construct($action, $publication) {
		$this->action = $action;

		$this->addField(new FieldTextarea('citationsRaw', [
				'label' => __('submission.citations'),
				'description' => __('submission.citations.description'),
				'value' => $publication->getData('citationsRaw'),
			]));
	}
}
