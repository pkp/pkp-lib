<?php
/**
 * @file classes/components/forms/invitation/AcceptUserDetailsForm.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AcceptUserDetailsForm
 *
 *
 * @brief Handles accept invitation user details form
 */

namespace PKP\components\forms\invitation;

use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\facades\Locale;

class AcceptUserDetailsForm extends FormComponent
{
    public const ACCEPT_FORM_USER_DETAILS = 'acceptUserDetails';
    /** @copydoc FormComponent::$id */
    public $id = self::ACCEPT_FORM_USER_DETAILS;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     */
    public function __construct($action, $locales)
    {
        $this->action = $action;
        $this->locales = $locales;

        $countries = [];
        foreach (Locale::getCountries() as $country) {
            $countries[] = [
                'value' => $country->getAlpha2(),
                'label' => $country->getLocalName()
            ];
        }

        usort($countries, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        $this->addField(new FieldText('givenName', [
            'label' => __('user.givenName'),
            'description' => __('acceptInvitation.userDetailsForm.givenName.description'),
            'isRequired' => true,
            'isMultilingual' => true,
            'size' => 'large',
            'value' => ''
        ]))
            ->addField(new FieldText('familyName', [
                'label' => __('user.familyName'),
                'description' => __('acceptInvitation.userDetailsForm.familyName.description'),
                'isRequired' => false,
                'isMultilingual' => true,
                'size' => 'large',
                'value' => ''
            ]))
            ->addField(new FieldText('affiliation', [
                'label' => __('user.affiliation'),
                'description' => __('acceptInvitation.userDetailsForm.affiliation.description'),
                'isMultilingual' => true,
                'isRequired' => false,
                'size' => 'large',

            ]))
            ->addField(new FieldSelect('userCountry', [
                'label' => __('acceptInvitation.userDetailsForm.countryOfAffiliation.label'),
                'description' => __('acceptInvitation.userDetailsForm.countryOfAffiliation.description'),
                'options' => $countries,
                'isRequired' => true,
                'size' => 'large',
            ]));

    }
}
