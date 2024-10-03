<?php

/**
 * @file classes/invitation/invitations/changeProfileEmail/payload/ChangeProfileEmailInvitePayload.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ChangeProfileEmailInvitePayload
 *
 * @brief Payload for the ChangeProfileEmailInvite invitation
 */

namespace PKP\invitation\invitations\changeProfileEmail\payload;

use PKP\invitation\core\InvitePayload;

class ChangeProfileEmailInvitePayload extends InvitePayload
{
    public function __construct(
        public ?string $newEmail = null,
    ) 
    {
        parent::__construct(get_object_vars($this));
    }
}
