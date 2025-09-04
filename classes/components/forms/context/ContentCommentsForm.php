<?php

/**
 * @file classes/components/form/context/ContentCommentsForm.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContentCommentsForm
 *
 * @ingroup classes_components_form
 *
 * @brief A preset form for general website content public comment settings.
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;

class ContentCommentsForm extends FormComponent
{
    public const FORM_CONTENT_COMMENT = 'contentComment';
    public $id = self::FORM_CONTENT_COMMENT;
    public $method = 'PUT';

    public function __construct($action, $locales, $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldOptions('enablePublicComments', [
            'label' => __('manager.userComment.comments'),
            'type' => 'checkbox',
            'value' => $context->getData('enablePublicComments'),
            'options' => [
                ['value' => true, 'label' => __('manager.userComment.enableComments')],
            ],
        ]));
    }
}
