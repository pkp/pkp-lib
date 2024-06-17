<?php
/**
 * @file classes/components/form/dashboard/SubmissionFilters.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilters
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form to add and remove filters in the submissions dashboard
 */

namespace PKP\components\forms\decision;

use PKP\components\forms\FieldRadioInput;
use PKP\components\forms\FormComponent;
use PKP\context\Context;

class LogReviewerResponseForm extends FormComponent
{
    public $id = 'logReviewerResponse';
    public $action = FormComponent::ACTION_EMIT;

    /** @copydoc FormComponent::$method */
    public $method = 'POST';

    public function __construct(
        string $action,
        array $locales,
        public Context $context,
    ) {
        $this->action = $action;
        $this->locales = $locales;
        $this->addField(new FieldRadioInput('acceptReview', [
            'groupId' => 'default',
            'label' => __('editor.review.logResponse.form.detail'),
            'options' => [
                [
                    'value' => 1,
                    'label' => __('editor.review.logResponse.form.option.accepted'),
                ],
                [
                    'value' => 0,
                    'label' => __('editor.review.logResponse.form.option.declined'),
                ],
            ],
            'value' => false,
            'type' => 'radio',
            'isRequired' => true,
            'description' => __('editor.review.logResponse.form.subDetail'),
        ]))->addGroup([
            'id' => 'default',
            'pageId' => 'default',
        ])->addPage([
            'id' => 'default',
            'submitButton' => ['label' => __('editor.review.logResponse')]
        ]);
    }
}
