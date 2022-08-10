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

use PKP\controllers\grid\users\stageParticipant\form\PKPStageParticipantNotifyForm;

class StageParticipantNotifyForm extends PKPStageParticipantNotifyForm
{
    /**
     * FIXME should be retrieved from a database based on a record in email_template_assignments table after
     * API implementation pkp/pkp-lib#7706
     */
    protected function getStageTemplates(): array
    {
        return ['EDITOR_ASSIGN'];
    }
}
