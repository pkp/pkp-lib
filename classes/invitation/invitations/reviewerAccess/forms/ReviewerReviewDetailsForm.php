<?php

namespace PKP\invitation\invitations\reviewerAccess\forms;

use PKP\components\forms\FieldControlledVocab;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\controlledVocab\ControlledVocab;
use PKP\core\Core;
use PKP\submission\reviewAssignment\ReviewAssignment;

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

        $this->addField(new FieldOptions('reviewMethod', [
            'label' => __('reviewerInvitation.reviewMethod'),
            'isRequired' => true,
            'size' => 'large',
            'type' => 'radio',
            'options' => [
                [
                    'value' => ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS,
                    'label' => __('reviewerInvitation.reviewMethod.anonymousAuthorOrReviewer'),
                ],
                [
                    'value' => ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS,
                    'label' => __('reviewerInvitation.reviewMethod.disclosedAuthor'),
                ],
                [
                    'value' => ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN,
                    'label' => __('reviewerInvitation.reviewMethod.open'),
                ],
            ],
        ]));

        $this->addField(new FieldText('responseDueDate', [
            'label' => __('reviewerInvitation.responseDueDate'),
            'inputType' => 'date',
            'value'=> Core::getCurrentDate(),
            'isRequired' => true,
        ]))->addField(new FieldText('reviewDueDate', [
            'label' => __('reviewerInvitation.reviewDueDate'),
            'inputType' => 'date',
            'value'=> Core::getCurrentDate(),
            'isRequired' => true,
        ]));

    }
}
