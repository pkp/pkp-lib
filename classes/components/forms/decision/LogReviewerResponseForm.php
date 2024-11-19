<?php
/**
 * @file classes/components/form/deecision/LogReviewerResponseForm.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LogReviewerResponseForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A form to accept or decline a review request on behalf of a reviewer
 */

namespace PKP\components\forms\decision;

use PKP\components\forms\FieldRadioInput;
use PKP\components\forms\FormComponent;
use PKP\context\Context;

class LogReviewerResponseForm extends FormComponent
{
    public $id = 'logReviewerResponse';
    public $action = FormComponent::ACTION_EMIT;
    public $method = 'PUT';

    public function __construct(
        array $locales,
        public Context $context,
    ) {
        $this->locales = $locales;
        $this->addField(new FieldRadioInput('decision', [
            'groupId' => 'default',
            'label' => __('editor.review.logResponse.form.detail'),
            'options' => [
                [
                    'value' => 'accept',
                    'label' => __('editor.review.logResponse.form.option.accepted'),
                ],
                [
                    'value' => 'decline',
                    'label' => __('editor.review.logResponse.form.option.declined'),
                ],
            ],
            'value' => '',
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
