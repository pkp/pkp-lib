<?php

/**
 * @file classes/components/form/funder/FunderEditForm.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FunderEditForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's single funder
 */

namespace PKP\components\forms\funder;

use PKP\components\forms\FieldText;
use PKP\components\forms\FieldFunder;
use PKP\components\forms\FieldFunderGrants;
use PKP\components\forms\FormComponent;

class FunderEditForm extends FormComponent
{
    public const FORM_FUNDER_EDIT = 'funder';
    public $id = self::FORM_FUNDER_EDIT;
    public $method = 'POST';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     */
    public function __construct(string $action)
    {
        $this->action = $action;

        $this->addField(new FieldFunder('funder', [
            'label' => __('submission.funders.funder.label.name'),
            'isMultilingual' => false,
        ]));
        $this->addField(new FieldFunderGrants('grants', [
            'label' => __('submission.funders.funder.grants.label.name'),
            'description' => __('submission.funders.funder.grants.label.description'),
            'value' => null,
        ]));
    }
}
