<?php

namespace PKP\components\forms\invitations;

use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

define('FORM_USER_SEARCH', 'userSearch');
class SearchUserForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_USER_SEARCH;

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
            ->addField(new FieldText('username', [
                'label' => __('user.username'),
                'isRequired' => true,
            ]))
            ->addField(new FieldText('orcid_id', [
                'label' => __('user.orcid_id'),
                'isRequired' => true,
            ]));
    }
}
