<?php
/**
 * @file classes/decision/steps/Form.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A step in an editorial decision workflow that shows a form to be completed
 */

namespace PKP\decision\steps;

use PKP\components\forms\FormComponent;
use PKP\decision\Step;
use stdClass;

class Form extends Step
{
    public string $type = 'form';
    public FormComponent $form;

    /**
     * @param FormComponent $form The form to show in this step
     */
    public function __construct(string $id, string $name, string $description, FormComponent $form)
    {
        parent::__construct($id, $name, $description);
        $this->form = $form;
    }

    public function getState(): stdClass
    {
        $config = parent::getState();
        $config->form = $this->form->getConfig();

        // Decision forms shouldn't have submit buttons
        // because the step-by-step decision wizard includes
        // next/previous buttons
        unset($config->form['pages'][0]['submitButton']);

        return $config;
    }
}
