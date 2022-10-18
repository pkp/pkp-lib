<?php
/**
 * @file classes/components/form/publication/PKPCitationsForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCitationsForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for setting a publication's citations
 */

namespace PKP\components\forms\publication;

use APP\publication\Publication;
use PKP\components\forms\FieldTextarea;
use PKP\components\forms\FormComponent;

define('FORM_CITATIONS', 'citations');

class PKPCitationsForm extends FormComponent
{
    public $id = FORM_CITATIONS;
    public $method = 'PUT';
    public bool $isRequired;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     */
    public function __construct(string $action, Publication $publication, bool $isRequired = false)
    {
        $this->action = $action;
        $this->isRequired = $isRequired;

        $this->addField(new FieldTextarea('citationsRaw', [
            'label' => __('submission.citations'),
            'description' => __('submission.citations.description'),
            'value' => $publication->getData('citationsRaw'),
            'isRequired' => $isRequired
        ]));
    }
}
