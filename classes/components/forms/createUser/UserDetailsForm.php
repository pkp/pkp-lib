<?php

namespace PKP\components\forms\createUser;

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
     */
    public function __construct($action, $locales, $data)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldText('orcid', [
            'label' => __('user.orcid'),
            'isRequired' => false,
            'size' => 'large',
            'value' => $data->orcid
        ]))
            ->addField(new FieldText('givenName', [
                'label' => __('user.givenName'),
                'isRequired' => true,
                'size' => 'large',
                'value' => $data->givenName
            ]))
            ->addField(new FieldText('familyName', [
                'label' => __('user.familyName'),
                'isRequired' => true,
                'size' => 'large',
                'value' => $data->familyName
            ]))
            ->addField(new FieldText('affiliation', [
                'label' => __('user.affiliation'),
                'isRequired' => true,
                'size' => 'large',

            ]))
            ->addField(new FieldText('country', [
                'label' => __('user.countyOfAffiliation'),
                'isRequired' => true,
                'size' => 'large',
            ]));

    }
}
