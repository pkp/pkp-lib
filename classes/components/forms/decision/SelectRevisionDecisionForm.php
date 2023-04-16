<?php
/**
 * @file classes/components/form/decision/SelectRevisionDecisionForm.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SelectRevisionDecisionForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for selecting between revisions or resubmit for review.
 */

namespace PKP\components\forms\decision;

use APP\decision\Decision;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FormComponent;

define('FORM_SELECT_REVISION_DECISION', 'selectRevisionDecision');

class SelectRevisionDecisionForm extends FormComponent
{
    /** @copydoc FormComponent::$id */
    public $id = FORM_SELECT_REVISION_DECISION;

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
                    'value' => Decision::PENDING_REVISIONS,
                    'label' => __('editor.review.NotifyAuthorRevisions'),
                ],
                [
                    'value' => Decision::RESUBMIT,
                    'label' => __('editor.review.NotifyAuthorResubmit'),
                ],
            ],
            'value' => Decision::PENDING_REVISIONS,
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
