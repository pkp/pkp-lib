<?php
/**
 * @file classes/components/form/announcement/PKPAnnouncementForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for creating a new announcement
 */

namespace PKP\components\forms\announcement;

use PKP\announcement\AnnouncementTypeDAO;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldUploadImage;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\db\DAORegistry;

class PKPAnnouncementForm extends FormComponent
{
    public const FORM_ANNOUNCEMENT = 'announcement';
    public $id = self::FORM_ANNOUNCEMENT;
    public $method = 'POST';
    public ?Context $context;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     */
    public function __construct($action, $locales, string $baseUrl, string $temporaryFileApiUrl, ?Context $context = null)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->context = $context;

        $announcementTypeOptions = $this->getAnnouncementTypeOptions();

        $this->addField(new FieldText('title', [
            'label' => __('common.title'),
            'size' => 'large',
            'isMultilingual' => true,
        ]))
            ->addField(new FieldRichTextarea('descriptionShort', [
                'label' => __('manager.announcements.form.descriptionShort'),
                'description' => __('manager.announcements.form.descriptionShortInstructions'),
                'isMultilingual' => true,
            ]))
            ->addField(new FieldRichTextarea('description', [
                'label' => __('manager.announcements.form.description'),
                'description' => __('manager.announcements.form.descriptionInstructions'),
                'isMultilingual' => true,
                'size' => 'large',
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                'plugins' => 'paste,link,lists',
            ]))
            ->addField(new FieldUploadImage('image', [
                'label' => __('manager.image'),
                'baseUrl' => $baseUrl,
                'options' => [
                    'url' => $temporaryFileApiUrl,
                ],
            ]))
            ->addField(new FieldText('dateExpire', [
                'label' => __('manager.announcements.form.dateExpire'),
                'description' => __('manager.announcements.form.dateExpireInstructions'),
                'size' => 'small',
            ]));
        if (!empty($announcementTypeOptions)) {
            $this->addField(new FieldOptions('typeId', [
                'label' => __('manager.announcementTypes.typeName'),
                'type' => 'radio',
                'options' => $announcementTypeOptions,
            ]));
        }

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

    protected function getAnnouncementTypeOptions(): array
    {
        /** @var AnnouncementTypeDAO */
        $announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO');

        $announcementTypes = $announcementTypeDao->getByContextId($this->context?->getId());

        $announcementTypeOptions = [];
        foreach ($announcementTypes as $announcementType) {
            $announcementTypeOptions[] = [
                'value' => (int) $announcementType->getId(),
                'label' => $announcementType->getLocalizedTypeName(),
            ];
        }

        return $announcementTypeOptions;
    }
}
