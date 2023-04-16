<?php
/**
 * @file classes/components/form/context/AccessForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AccessForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring the terms under which a server will
 *  allow access to its published content.
 */

namespace APP\components\forms\context;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;

define('FORM_ACCESS', 'access');

class AccessForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_ACCESS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param Context $context Journal, Server or Press to change settings for
     */
    public function __construct($action, $locales, $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldOptions('publishingMode', [
            'label' => __('manager.distribution.publishingMode'),
            'type' => 'radio',
            'options' => [
                ['value' => \APP\server\Server::PUBLISHING_MODE_OPEN, 'label' => __('manager.distribution.publishingMode.openAccess')],
                ['value' => \APP\server\Server::PUBLISHING_MODE_NONE, 'label' => __('manager.distribution.publishingMode.none')],
            ],
            'value' => $context->getData('publishingMode'),
        ]))
            ->addField(new FieldOptions('enableOai', [
                'label' => __('manager.setup.enableOai'),
                'description' => __('manager.setup.enableOai.description'),
                'type' => 'radio',
                'options' => [
                    ['value' => true, 'label' => __('common.enable')],
                    ['value' => false, 'label' => __('common.disable')],
                ],
                'value' => $context->getData('enableOai'),
            ]));
    }
}
