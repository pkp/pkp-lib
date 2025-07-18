<?php

/**
 * @file classes/components/form/citation/CitationRawEditForm.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationRawEditForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's single citation
 */

namespace PKP\components\forms\citation;

use PKP\components\forms\FieldTextarea;
use PKP\components\forms\FormComponent;

class CitationRawEditForm extends FormComponent
{
    public const FORM_CITATION_RAW = 'citation_raw';
    public $id = self::FORM_CITATION_RAW;
    public $method = 'PUT';
    public bool $isRequired;

    public function __construct(string $action, ?int $citationId, bool $isRequired = false)
    {
        $this->action = $action;
        $this->isRequired = $isRequired;

        $this->addField(new FieldTextarea('citationsRaw', [
            'label' => __('submission.citations'),
            'description' => __('submission.citations.description'),
            'value' => null,
            'isRequired' => $isRequired
        ]));
    }
}
