<?php

/**
 * @file controllers/grid/users/stageParticipant/form/StageParticipantNotifyForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageParticipantNotifyForm
 * @ingroup grid_users_stageParticipant_form
 *
 * @brief Form to notify a user regarding a file
 */

namespace APP\controllers\grid\users\stageParticipant\form;

use APP\mail\PreprintMailTemplate;
use PKP\controllers\grid\users\stageParticipant\form\PKPStageParticipantNotifyForm;

class StageParticipantNotifyForm extends PKPStageParticipantNotifyForm
{
    /**
     * Return app-specific stage templates.
     *
     * @return array
     */
    protected function _getStageTemplates()
    {
        return [
            WORKFLOW_STAGE_ID_PRODUCTION => ['EDITOR_ASSIGN']
        ];
    }

    /**
     * return app-specific mail template.
     *
     * @param Submission $submission
     * @param string $templateKey
     * @param bool $includeSignature optional
     *
     * @return array
     */
    protected function _getMailTemplate($submission, $templateKey, $includeSignature = true)
    {
        if ($includeSignature) {
            return new PreprintMailTemplate($submission, $templateKey);
        } else {
            return new PreprintMailTemplate($submission, $templateKey, null, null, null, false);
        }
    }
}
