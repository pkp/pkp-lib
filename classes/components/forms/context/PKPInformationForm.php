<?php
/**
 * @file classes/components/form/context/PKPInformationForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInformationForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring the information fields for a
 *  context (eg - info for readers, authors and librarians).
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FormComponent;

class PKPInformationForm extends FormComponent
{
    public const FORM_INFORMATION = 'information';
    public $id = self::FORM_INFORMATION;
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param \PKP\context\Context $context Journal or Press to change settings for
     * @param string $imageUploadUrl The API endpoint for images uploaded through the rich text field
     */
    public function __construct($action, $locales, $context, $imageUploadUrl)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addGroup([
            'id' => 'descriptions',
            'label' => __('manager.setup.information.descriptionTitle'),
            'description' => __('manager.setup.information.description'),
        ])
            ->addField(new FieldRichTextarea('readerInformation', [
                'label' => __('manager.setup.information.forReaders'),
                'isMultilingual' => true,
                'groupId' => 'descriptions',
                'value' => $context->getData('readerInformation'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
                'plugins' => ['link','lists','image','code'],
                'uploadUrl' => $imageUploadUrl,
            ]))
            ->addField(new FieldRichTextarea('authorInformation', [
                'label' => __('manager.setup.information.forAuthors'),
                'isMultilingual' => true,
                'groupId' => 'descriptions',
                'value' => $context->getData('authorInformation'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
                'plugins' => ['link','lists','image','code'],
                'uploadUrl' => $imageUploadUrl,
            ]))
            ->addField(new FieldRichTextarea('librarianInformation', [
                'label' => __('manager.setup.information.forLibrarians'),
                'isMultilingual' => true,
                'groupId' => 'descriptions',
                'value' => $context->getData('librarianInformation'),
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist | image | code',
                'plugins' => ['link','lists','image','code'],
                'uploadUrl' => $imageUploadUrl,
            ]));
    }
}
