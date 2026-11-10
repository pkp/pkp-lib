<?php

/**
 * @file classes/components/form/publication/PKPFundingStatementForm.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPFundingStatementForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's funding statement
 */

namespace PKP\components\forms\publication;

use APP\publication\Publication;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FormComponent;

class PKPFundingStatementForm extends FormComponent
{
    public const FORM_FUNDING_STATEMENT = 'fundingStatement';
    public $id = self::FORM_FUNDING_STATEMENT;
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     */
    public function __construct(string $action, array $locales, Publication $publication, bool $fundingStatementSetting, bool $isRequired = false)
    {
        $this->action = $action;
        $this->locales = $locales;

        if ($fundingStatementSetting) {
            $this->addField(new FieldRichTextarea('fundingStatement', [
                'label' => __('submission.fundingStatement'),
                'description' => __('manager.setup.metadata.fundingStatement.description'),
                'isMultilingual' => true,
                'value' => $publication->getData('fundingStatement'),
                'isRequired' => $isRequired
            ]));
        }
    }
}
