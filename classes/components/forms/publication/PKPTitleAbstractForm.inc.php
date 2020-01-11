<?php
/**
 * @file classes/components/form/publication/PKPTitleAbstractForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPTitleAbstractForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's title and abstract
 */
namespace PKP\components\forms\publication;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldText;
use \PKP\components\forms\FieldRichTextarea;

define('FORM_TITLE_ABSTRACT', 'titleAbstract');

class PKPTitleAbstractForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_TITLE_ABSTRACT;

	/** @copydoc FormComponent::$method */
	public $method = 'PUT';

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $locales array Supported locales
	 * @param $publication Publication The publication to change settings for
	 */
	public function __construct($action, $locales, $publication) {
		$this->action = $action;
		$this->successMessage = __('publication.titleAbstract.success');
		$this->locales = $locales;

		$this->addField(new FieldText('prefix', [
				'label' => __('common.prefix'),
				'description' => __('common.prefixAndTitle.tip'),
				'size' => 'small',
				'isMultilingual' => true,
				'value' => $publication->getData('prefix'),
			]))
			->addField(new FieldText('title', [
				'label' => __('common.title'),
				'size' => 'large',
				'isMultilingual' => true,
				'value' => $publication->getData('title'),
			]))
			->addField(new FieldText('subtitle', [
				'label' => __('common.subtitle'),
				'size' => 'large',
				'isMultilingual' => true,
				'value' => $publication->getData('subtitle'),
			]))
			->addField(new FieldRichTextarea('abstract', [
				'label' => __('common.abstract'),
				'isMultilingual' => true,
				'value' => $publication->getData('abstract'),
			]));
	}
}
