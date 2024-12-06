<?php
/**
 * @file classes/components/form/context/ReviewerRecommendationForm.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerRecommendationForm
 *
 * @brief 
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;

class ReviewerRecommendationForm extends FormComponent
{
    public const FORM_REVIEW_RECOMMENDATION = 'reviewerRecommendation';
    public $id = self::FORM_REVIEW_RECOMMENDATION;
    public $method = 'POST';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param \PKP\context\Context $context Journal or Press to change settings for
     */
    public function __construct($action, $locales)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this
            ->addField(new FieldText('title', [
                'label' => __('manager.reviewerRecommendations.title.label'),
                'description' => __('manager.reviewerRecommendations.title.description'),
                'size' => 'large',
                'isRequired' => true,
                'isMultilingual' => true,
            ]))
            ->addField(new FieldSelect('status', [
                'options' => [
                    [
                        'label' => __('manager.reviewerRecommendations.activateUponSaving.label'),
                        'value' => 1,
                    ],
                    [
                        'label' => __('manager.reviewerRecommendations.activateUponSaving.deactivate'),
                        'value' => 0,
                    ],
                ],
                'value' => 1,
                'size' => 'normal',
            ]));
    }
}
