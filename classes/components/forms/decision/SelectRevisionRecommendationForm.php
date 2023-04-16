<?php
/**
 * @file classes/components/form/decision/SelectRevisionRecommendationForm.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SelectRevisionRecommendationForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for selecting between revisions or resubmit for review.
 */

namespace PKP\components\forms\decision;

use APP\decision\Decision;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;

define('FORM_SELECT_REVISION_RECOMMENDATION', 'selectRevisionRecommendation');

class SelectRevisionRecommendationForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_SELECT_REVISION_RECOMMENDATION;

    /** @copydoc FormComponent::$action */
    public $action = FormComponent::ACTION_EMIT;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->addField(new FieldOptions('decision', [
            'label' => __('editor.review.newReviewRound'),
            'type' => 'radio',
            'options' => [
                [
                    'value' => Decision::RECOMMEND_PENDING_REVISIONS,
                    'label' => __('editor.review.NotifyAuthorRevisions.recommendation'),
                ],
                [
                    'value' => Decision::RECOMMEND_RESUBMIT,
                    'label' => __('editor.review.NotifyAuthorResubmit.recommendation'),
                ],
            ],
            'value' => Decision::RECOMMEND_PENDING_REVISIONS,
            'groupId' => 'default',
        ]))
            ->addGroup([
                'id' => 'default',
                'pageId' => 'default',
            ])
            ->addPage([
                'id' => 'default',
                'submitButton' => ['label' => __('help.next')]
            ]);
    }
}
