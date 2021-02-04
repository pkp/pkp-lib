<?php
/**
 * @file classes/components/form/context/PKPSubmissionFileForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFileForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for editing a submission file
 */
namespace PKP\components\forms\submission;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldOptions;

define('FORM_SUBMISSION_FILE', 'submissionFile');

class PKPSubmissionFileForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_SUBMISSION_FILE;

	/** @copydoc FormComponent::$method */
	public $method = 'PUT';

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $genres array List of genres to use as options
	 */
	public function __construct($action, $genres) {
		$this->action = $action;

		$this->addField(new FieldOptions('genreId', [
			'label' => __('submission.submit.genre.label'),
			'description' => __('submission.submit.genre.description'),
			'type' => 'radio',
			'options' => array_map(function($genre) {
				return [
					'value' => (int) $genre->getId(),
					'label' => $genre->getLocalizedName(),
				];
			}, $genres),
			'value' => 0,
		]));
	}
}
