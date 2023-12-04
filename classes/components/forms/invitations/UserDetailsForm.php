<?php

namespace PKP\components\forms\invitations;

use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

define('FORM_USER_DETAILS', 'userDetails');
class UserDetailsForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_USER_DETAILS;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

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

        $this->addField(new FieldText('email', [
            'label' => __('user.email'),
            'isRequired' => true,
        ]))
            ->addField(new FieldText('givenName', [
                'label' => __('user.givenName'),
                'isRequired' => true,
            ]))
            ->addField(new FieldText('familyName', [
                'label' => __('user.familyName'),
                'isRequired' => true,
            ]));
    }
}
