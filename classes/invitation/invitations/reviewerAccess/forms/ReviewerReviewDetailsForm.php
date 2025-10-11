<?php

namespace PKP\invitation\invitations\reviewerAccess\forms;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

class ReviewerReviewDetailsForm extends FormComponent
{
    public const FORM_REVIEWER_REVIEW_DETAILS = 'reviewerReviewDetails';
    /** @copydoc FormComponent::$id */
    public $id = self::FORM_REVIEWER_REVIEW_DETAILS;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     */
    public function __construct(string $action, array $locales)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this->addField(new FieldOptions('reviewTypes', [
            'label' => __('reviewerInvitation.reviewTypes'),
            'isRequired' => true,
            'size' => 'large',
            'type' => 'radio',
            'options' => [
                [
                    'value' => 'anonymous',
                    'label' => __('reviewerInvitation.reviewTypes.anonymousAuthorOrReviewer'),
                ],
                [
                    'value' => 'disclosed',
                    'label' => __('reviewerInvitation.reviewTypes.disclosedAuthor'),
                ],
                [
                    'value' => 'open',
                    'label' => __('reviewerInvitation.reviewTypes.open'),
                ],
            ],
        ]));

        $this->addField(new FieldText('responseDueDate', [
            'label' => __('reviewerInvitation.responseDueDate'),
            'inputType' => 'date',
            'isRequired' => true,
        ]))->addField(new FieldText('reviewDueDate', [
            'label' => __('reviewerInvitation.reviewDueDate'),
            'isRequired' => true,
            'inputType' => 'date'
        ]));

    }
}
