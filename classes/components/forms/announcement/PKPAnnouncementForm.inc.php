<?php
/**
 * @file classes/components/form/announcement/PKPAnnouncementForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for creating a new announcement
 */
namespace PKP\components\forms\announcement;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldOptions;
use \PKP\components\forms\FieldText;
use \PKP\components\forms\FieldRichTextarea;

define('FORM_ANNOUNCEMENT', 'announcement');

class PKPAnnouncementForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_ANNOUNCEMENT;

	/** @copydoc FormComponent::$method */
	public $method = 'POST';

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $locales array Supported locales
	 * @param $announcementContext Context The context to get supported announcement types
	 * @param $temporaryFileApiUrl string URL to upload files to
	 * @param $imageUploadUrl string The API endpoint for images uploaded through the rich text field
	 */
	public function __construct($action, $locales, $announcementContext, $temporaryFileApiUrl, $imageUploadUrl) {
		$this->action = $action;
		$this->locales = $locales;

		$this->addField(new FieldText('title', [
				'label' => __('common.title'),
				'size' => 'large',
				'isMultilingual' => true,
			]))
			->addField(new FieldRichTextarea('descriptionShort', [
				'label' => __('manager.announcements.form.descriptionShort'),
				'description' => __('manager.announcements.form.descriptionShortInstructions'),
				'isMultilingual' => true,
				'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
				'plugins' => 'paste,link,image,lists,code',
				'uploadUrl' => $imageUploadUrl,
				'options' => [
					'url' => $temporaryFileApiUrl,
				],
			]))
			->addField(new FieldRichTextarea('description', [
				'label' => __('manager.announcements.form.description'),
				'description' => __('manager.announcements.form.descriptionInstructions'),
				'isMultilingual' => true,
				'size' => 'large',
				'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
				'plugins' => 'paste,link,image,lists,code',
				'uploadUrl' => $imageUploadUrl,
				'options' => [
					'url' => $temporaryFileApiUrl,
				],
			]))
			->addField(new FieldText('dateExpire', [
				'label' => __('manager.announcements.form.dateExpire'),
				'description' => __('manager.announcements.form.dateExpireInstructions'),
				'size' => 'small',
			]));

		$announcementTypeDao = \DAORegistry::getDAO('AnnouncementTypeDAO');
		$announcementTypes = $announcementTypeDao->getByAssoc(\Application::get()->getContextAssocType(), $announcementContext->getId());
		$announcementOptions = [];
		foreach ($announcementTypes as $announcementType) {
			$announcementOptions[] = [
				'value' => (int) $announcementType->getId(),
				'label' => htmlspecialchars($announcementType->getLocalizedTypeName()),
			];
		}
		if (!empty($announcementOptions)) $this->addField(new FieldOptions('typeId', [
			'label' => __('manager.announcementTypes.typeName'),
			'type' => 'radio',
			'options' => $announcementOptions,
		]));

		$this->addField(new FieldOptions('sendEmail', [
			'label' => __('common.sendEmail'),
			'options' => [
				[
					'value' => true,
					'label' => __('notification.sendNotificationConfirmation')
				]
			]
		]));
	}
}
