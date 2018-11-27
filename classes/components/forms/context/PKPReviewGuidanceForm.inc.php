<?php
/**
 * @file controllers/form/context/PKPReviewGuidanceForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewGuidanceForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring the guidance a reviewer should receive.
 */
namespace PKP\components\forms\context;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldRichTextarea;
use \PKP\components\forms\FieldShowEnsuringLink;


define('FORM_REVIEW_GUIDANCE', 'reviewerGuidance');

class PKPReviewGuidanceForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_REVIEW_GUIDANCE;

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
		$this->successMessage = __('manager.publication.reviewerGuidance.success');
		$this->locales = $locales;

		$this->addField(new FieldRichTextarea('reviewGuidelines', [
				'label' => __('manager.setup.reviewGuidelines'),
				'helpTopic' => 'settings',
				'helpSection' => 'workflow-review-guidelines',
				'isMultilingual' => true,
				'value' => $context->getData('reviewGuidelines'),
			]))
			->addField(new FieldRichTextarea('competingInterests', [
				'label' => __('manager.setup.competingInterests'),
				'helpTopic' => 'settings',
				'helpSection' => 'workflow-review-interests',
				'isMultilingual' => true,
				'value' => $context->getData('competingInterests'),
			]))
			->addField(new FieldShowEnsuringLink('showEnsuringLink', [
				'options' => [
					['value' => true, 'label' => __('manager.setup.reviewOptions.showBlindReviewLink')],
				],
				'value' => $context->getData('showEnsuringLink'),
			]));
	}
}
