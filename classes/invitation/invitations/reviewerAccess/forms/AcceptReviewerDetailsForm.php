<?php

namespace PKP\invitation\invitations\reviewerAccess\forms;

use APP\core\Application;
use APP\facades\Repo;
use PKP\components\forms\FieldControlledVocab;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\controlledVocab\ControlledVocab;
use PKP\facades\Locale;
use PKP\user\interest\UserInterest;

class AcceptReviewerDetailsForm extends FormComponent
{
    public const ACCEPT_FORM_REVIEWER_DETAILS = 'acceptReviewerDetails';
    /** @copydoc FormComponent::$id */
    public $id = self::ACCEPT_FORM_REVIEWER_DETAILS;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';
    public int $submissionId;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param string $suggestionUrlBase The base URL to get suggestions for controlled vocab.
     */
    public function __construct($action, $locales ,string $suggestionUrlBase ,$submissionId)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->submissionId = $submissionId;

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
            'value' => '',
        ]))
        ->addField(new FieldText('affiliation', [
            'label' => __('user.affiliation'),
            'description' => __('acceptInvitation.userDetailsForm.affiliation.description'),
            'isMultilingual' => true,
            'isRequired' => false,
            'size' => 'large',
            'value' => '',

        ]))
        ->addField(new FieldSelect('userCountry', [
            'label' => __('acceptInvitation.userDetailsForm.countryOfAffiliation.label'),
            'description' => __('acceptInvitation.userDetailsForm.countryOfAffiliation.description'),
            'options' => $countries,
            'isRequired' => true,
            'size' => 'large',
        ]));

        $this->addField(new FieldControlledVocab('userInterests', [
            'label' => __('user.interests'),
            'tooltip' => __('manager.setup.metadata.subjects.description'),
            'isMultilingual' => true,
            'apiUrl' => str_replace('__vocab__', UserInterest::CONTROLLED_VOCAB_INTEREST, $suggestionUrlBase),
            'locales' => $this->locales,
            'value' => [],
        ]));

    }
}
