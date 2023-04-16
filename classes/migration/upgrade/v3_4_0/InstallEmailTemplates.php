<?php

/**
 * @file classes/migration/upgrade/v3_4_0/InstallEmailTemplates.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InstallEmailTemplates
 *
 * @brief Install new email templates for 3.4
 */

namespace APP\migration\upgrade\v3_4_0;

class InstallEmailTemplates extends \PKP\migration\upgrade\v3_4_0\InstallEmailTemplates
{
    protected function getEmailTemplateKeys(): array
    {
        return [
            'EDITOR_DECISION_NOTIFY_OTHER_AUTHORS',
            'EDITOR_DECISION_REVERT_INITIAL_DECLINE',
            'DISCUSSION_NOTIFICATION',
            'SUBMISSION_SAVED_FOR_LATER',
            'SUBMISSION_NEEDS_EDITOR',
            'SUBMISSION_ACK_CAN_POST',
            'POSTED_NEW_VERSION_ACK',
        ];
    }

    protected function getAppVariableNames(): array
    {
        return [
            'contextName' => 'serverName',
            'contextUrl' => 'serverUrl',
            'contextSignature' => 'serverSignature',
        ];
    }
}
