<?php
/**
 * @file classes/components/form/context/PKPEmailTemplateForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailTemplateForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for adding and editing email templates.
 */

namespace PKP\components\forms\emailTemplate;

use PKP\components\forms\FieldPreparedContent;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

class EmailTemplateForm extends FormComponent
{
    public const FORM_EMAIL_TEMPLATE = 'editEmailTemplate';
    public $id = self::FORM_EMAIL_TEMPLATE;

    public function __construct(string $action, array $locales)
    {
        $this->action = $action;
        $this->method = 'POST';
        $this->locales = $locales;

        $this->addField(new FieldText('name', [
            'label' => __('common.name'),
            'description' => __('manager.emailTemplate.name.description'),
            'isMultilingual' => true,
        ]))
            ->addField(new FieldText('subject', [
                'label' => __('email.subject'),
                'isMultilingual' => true,
                'size' => 'large',
            ]))
            ->addField(new FieldPreparedContent('body', [
                'label' => __('email.body'),
                'size' => 'large',
                'isMultilingual' => true,
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                'plugins' => 'paste,link,lists',
            ]));
    }
}
