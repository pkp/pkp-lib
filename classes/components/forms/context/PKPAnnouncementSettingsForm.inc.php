<?php
/**
 * @file classes/components/form/context/PKPAnnouncementSettingsForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementSettingsForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for enabling and configuring announcements.
 */
namespace PKP\components\forms\context;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldOptions;
use \PKP\components\forms\FieldRichTextarea;
use \PKP\components\forms\FieldText;

define('FORM_ANNOUNCEMENT_SETTINGS', 'announcementSettings');

class PKPAnnouncementSettingsForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_ANNOUNCEMENT_SETTINGS;

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
		$this->successMessage = __('manager.setup.announcements.success');
		$this->locales = $locales;

		$this->addField(new FieldOptions('enableAnnouncements', [
				'label' => __('manager.setup.announcements'),
				'description' => __('manager.setup.enableAnnouncements.description'),
				'options' => [
					['value' => true, 'label' => __('manager.setup.enableAnnouncements.enable')]
				],
				'value' => (bool) $context->getData('enableAnnouncements'),
			]))
			->addField(new FieldRichTextarea('announcementsIntroduction', [
				'label' => __('manager.setup.announcementsIntroduction'),
				'tooltip' => __('manager.setup.announcementsIntroduction.description'),
				'isMultilingual' => true,
				'value' => $context->getData('announcementsIntroduction'),
				'showWhen' => 'enableAnnouncements',
			]))
			->addField(new FieldText('numAnnouncementsHomepage', [
				'label' => __('manager.setup.numAnnouncementsHomepage'),
				'description' => __('manager.setup.numAnnouncementsHomepage.description'),
				'size' => 'small',
				'value' => $context->getData('numAnnouncementsHomepage'),
				'showWhen' => 'enableAnnouncements',
			]));
	}
}
