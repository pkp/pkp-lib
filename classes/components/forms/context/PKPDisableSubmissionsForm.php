<?php
/**
 * @file classes/components/form/context/PKPDisableSubmissionsForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDisableSubmissionsForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief  A preset form for disabling new submissions.
 */

namespace PKP\components\forms\context;

use APP\core\Application;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;

class PKPDisableSubmissionsForm extends FormComponent
{
    public const FORM_DISABLE_SUBMISSIONS = 'disableSubmissions';
    public $id = self::FORM_DISABLE_SUBMISSIONS;
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param \PKP\context\Context $context Journal or Press to change settings for
     */
    public function __construct($action, $locales, $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $url = Application::get()->getRequest()->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_PAGE,
            null,
            'management',
            'settings',
            ['context'],
            null,
            'sections'
        );

        $description = __('manager.setup.disableSubmissions.description', ['url' => $url]);

        $this->addField(new FieldOptions('disableSubmissions', [
            'label' => __('manager.setup.disableSubmissions'),
            'description' => $description,
            'options' => [
                [
                    'value' => true,
                    'label' => __('manager.setup.disableSubmissions'),
                ],
            ],
            'value' => (bool) $context->getData('disableSubmissions'),
        ]));
    }
}
