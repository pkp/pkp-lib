<?php
/**
 * @file classes/components/form/publication/PKPPublicationLicenseForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicationLicenseForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's license and copyright info
 */
namespace PKP\components\forms\publication;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldText;
use \PKP\components\forms\FieldHTML;

define('FORM_PUBLICATION_LICENSE', 'publicationLicense');

class PKPPublicationLicenseForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_PUBLICATION_LICENSE;

	/** @copydoc FormComponent::$method */
	public $method = 'PUT';

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $locales array Supported locales
	 * @param $publication Publication The publication to change settings for
	 * @param $context Context The publication's context
	 */
	public function __construct($action, $locales, $publication, $context) {
		$this->action = $action;
		$this->successMessage = __('publication.publicationLicense.success');
		$this->locales = $locales;

		// Don't allow the user to set a copyright year when it is meant
		// to be set by the issue's publication date.
		if ($context->getData('copyrightYearBasis') === 'issue') {
			$copyrightYear = new FieldHTML('copyrightYear', [
				'label' => __('submission.copyrightYear'),
				'description' => __('publication.copyrightYearBasis.issueDescription'),
			]);
		} else {
			$copyrightYear = new FieldText('copyrightYear', [
				'label' => __('submission.copyrightYear'),
				'size' => 'small',
				'value' => $publication->getData('copyrightYear'),
			]);
		}

		$this->addField(new FieldText('copyrightHolder', [
				'label' => __('submission.copyrightHolder'),
				'size' => 'large',
				'isMultilingual' => true,
				'value' => $publication->getData('coyrightHolder'),
			]))
			->addField($copyrightYear)
			->addField(new FieldText('licenseUrl', [
				'label' => __('submission.licenseURL'),
				'size' => 'large',
				'value' => $publication->getData('licenseUrl'),
			]));
	}
}
