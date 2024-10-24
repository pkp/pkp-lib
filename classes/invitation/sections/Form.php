<?php
/**
 * @file classes/invitation/sections/Form.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Form
 *
 * @brief A section in an invitation workflow that shows a form.
 */
namespace PKP\invitation\sections;

use Exception;
use PKP\components\forms\FormComponent;
use stdClass;

class Form extends Section
{
    public string $type = 'form';
    public FormComponent $form;

    /**
     * @param FormComponent $form The form to show in this step
     *
     * @throws Exception
     */
    public function __construct(string $id, string $name, string $description, FormComponent $form)
    {
        parent::__construct($id, $name, $description);
        $this->form = $form;
    }

    public function getState(): stdClass
    {
        $config = parent::getState();
        foreach ($this->form->getConfig() as $key => $value) {
            $config->$key = $value;
        }
        unset($config->pages[0]['submitButton']);

        return $config;
    }
}
