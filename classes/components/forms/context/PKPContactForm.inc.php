<?php
/**
 * @file classes/components/form/context/PKPContactForm.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContactForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a context's contact details.
 */
namespace PKP\components\forms\context;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldText;
use \PKP\components\forms\FieldTextarea;

define('FORM_CONTACT', 'contact');

class PKPContactForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_CONTACT;

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
		$this->successMessage = __('manager.setup.contact.success');
		$this->locales = $locales;

		$this->addGroup([
				'id' => 'principal',
				'label' => __('manager.setup.principalContact'),
				'description' => __('manager.setup.principalContactDescription'),
			])
			->addField(new FieldText('contactName', [
				'label' => __('common.name'),
				'isRequired' => true,
				'groupId' => 'principal',
				'value' => $context->getData('contactName'),
			]))
			->addField(new FieldText('contactEmail', [
				'label' => __('user.email'),
				'isRequired' => true,
				'groupId' => 'principal',
				'value' => $context->getData('contactEmail'),
			]))
			->addField(new FieldText('contactPhone', [
				'label' => __('user.phone'),
				'groupId' => 'principal',
				'value' => $context->getData('contactPhone'),
			]))
			->addField(new FieldText('contactAffiliation', [
				'label' => __('user.affiliation'),
				'isMultilingual' => true,
				'groupId' => 'principal',
				'value' => $context->getData('contactAffiliation'),
			]))
			->addField(new FieldTextarea('mailingAddress', [
				'label' => __('common.mailingAddress'),
				'isRequired' => true,
				'size' => 'small',
				'groupId' => 'principal',
				'value' => $context->getData('mailingAddress'),
			]))
			->addGroup([
				'id' => 'technical',
				'label' => __('manager.setup.technicalSupportContact'),
				'description' => __('manager.setup.technicalSupportContactDescription'),
			])
			->addField(new FieldText('supportName', [
				'label' => __('common.name'),
				'isRequired' => true,
				'groupId' => 'technical',
				'value' => $context->getData('supportName'),
			]))
			->addField(new FieldText('supportEmail', [
				'label' => __('user.email'),
				'isRequired' => true,
				'groupId' => 'technical',
				'value' => $context->getData('supportEmail'),
			]))
			->addField(new FieldText('supportPhone', [
				'label' => __('user.phone'),
				'groupId' => 'technical',
				'value' => $context->getData('supportPhone'),
			]));
	}
}
