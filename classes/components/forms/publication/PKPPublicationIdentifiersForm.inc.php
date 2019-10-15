<?php
/**
 * @file classes/components/form/publication/PKPPublicationIdentifiersForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicationIdentifiersForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's public identifiers (DOI, etc)
 */
namespace PKP\components\forms\publication;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldText;

define('FORM_PUBLICATION_IDENTIFIERS', 'publicationIdentifiers');

class PKPPublicationIdentifiersForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_PUBLICATION_IDENTIFIERS;

	/** @copydoc FormComponent::$method */
	public $method = 'PUT';

	/** @var Publication The publication this form is for */
	public $publication;

	/** @var Context The journal/press this publication exists in */
	public $submissionContext;

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $locales array Supported locales
	 * @param $publication Publication The publication to change settings for
	 * @param $submissionContext Context The journal/press this publication exists in
	 */
	public function __construct($action, $locales, $publication, $submissionContext) {
		$this->action = $action;
		$this->successMessage = __('publication.publicationIdentifiers.success');
		$this->locales = $locales;
		$this->publication = $publication;
		$this->submissionContext = $submissionContext;

		$this->addField(new FieldText('pub-id::publisher-id', [
			'label' => __('submission.publisherId'),
			'value' => $publication->getData('pub-id::publisher-id'),
		]));
	}
}
