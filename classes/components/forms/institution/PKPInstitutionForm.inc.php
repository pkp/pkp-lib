<?php
/**
 * @file classes/components/form/institution/PKPInstitutionForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInstitutionForm
 * @ingroup classes_controllers_form
 *
 * @brief A form for creating a new institution
 */

namespace PKP\components\forms\institution;

use PKP\components\forms\FieldText;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\FormComponent;

define('FORM_INSTITUTION', 'institution');

class PKPInstitutionForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_INSTITUTION;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     */
    public function __construct(string $action, array $locales)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldText('name', [
            'label' => __('common.name'),
            'size' => 'large',
            'isMultilingual' => true,
        ]))
            ->addField(new FieldTextarea('ipRanges', [
                'label' => __('manager.institutions.form.ipRanges'),
                'description' => __('manager.institutions.form.ipRangesInstructions'),
                'isMultilingual' => false,
            ]));
    }
}
