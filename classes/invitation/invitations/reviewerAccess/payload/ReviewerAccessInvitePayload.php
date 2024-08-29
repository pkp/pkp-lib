<?php

/**
 * @file classes/invitation/invitations/reviewerAccess/payload/ReviewerAccessInvitePayload.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAccessInvitePayload
 *
 * @brief Payload for the ReviewerAccessInvite invitation
 */

namespace PKP\invitation\invitations\reviewerAccess\payload;

use PKP\invitation\core\InvitePayload;

class ReviewerAccessInvitePayload extends InvitePayload
{
    public function __construct(
        public ?int $reviewAssignmentId = null,
    ) 
    {
        parent::__construct(get_object_vars($this));
    }
}
