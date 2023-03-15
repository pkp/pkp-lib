<?php
/**
 * @file classes/components/form/context/PKPLicenseForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPLicenseForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a publication's license URL.
 */

namespace APP\components\forms\publication;

use APP\core\Application;
use APP\publication\Publication;
use PKP\components\forms\FieldRadioInput;
use PKP\components\forms\FormComponent;
use PKP\context\Context;

class LicenseUrlForm extends FormComponent
{
    public Context $context;
    public Publication $publication;
    public array $licenseOptions = [];

    public function __construct(string $id, string $method, string $action, Publication $publication, Context $context)
    {
        parent::__construct($id, $method, $action, []);

        $this->context = $context;
        $this->publication = $publication;

        $this->addLicenseField();
    }

    protected function addLicenseField(): void
    {
        $licenseOptions = Application::getCCLicenseOptions();
        foreach ($licenseOptions as $url => $label) {
            $this->licenseOptions[] = [
                'value' => $url,
                'label' => __($label),
            ];
        }
        $this->licenseOptions[] = [
            'value' => 'other',
            'label' => __('manager.distribution.license.other'),
            'isInput' => true,
        ];

        $this->addField(new FieldRadioInput('licenseUrl', [
            'label' => __('manager.distribution.license'),
            'type' => 'radio',
            'options' => $this->licenseOptions,
            'value' => $this->publication->getData('licenseUrl') ?? $this->context->getData('licenseUrl'),
        ]));
    }
}
