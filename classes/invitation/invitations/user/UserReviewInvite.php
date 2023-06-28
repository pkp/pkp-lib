<?php

/**
 * @file invitation/invitations/user/UserReviewInvite.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserReviewInvite
 *
 * @ingroup invitations
 *
 * @brief Reviewer Role Assignment specific invitation
 */

namespace PKP\invitation\invitations\user;

use APP\facades\Repo;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use PKP\invitation\invitations\BaseInvitation;
use PKP\invitation\invitations\enums\InvitationType;
use PKP\mail\mailables\DispatchInvitation;

class UserReviewInvite extends BaseInvitation
{
    /**
     * The review round id
     */
    protected int $reviewRoundId;

    protected ?int $invitedUserId;

    /**
     * Create a new invitation instance.
     */
    public function __construct(?int $invitedUserId, string $email, int $contextId, int $reviewRoundId)
    {
        $this->invitedUserId = $invitedUserId;
        $this->reviewRoundId = $reviewRoundId;

        parent::__construct(InvitationType::NEW_REVIEWER_INVITATION_TYPE, $email, $contextId, $reviewRoundId);
    }

    
    /**
     * @return \PKP\mail\Mailable
     */
    public function getInvitationMailable(): Mailable 
    {
        $message = 'This is an invitation for reviewer invitations'; // Should be localised
        $emailSubject = 'This is an invitation for reviewer invitations'; // Should be localised
        $acceptURL = $this->getAcceptInvitationURL();
        $declineURL = $this->getDeclineInvitationURL();

        // Should create a base mailable and a specific mailable for each invitation type
        return new DispatchInvitation($message, $emailSubject, $acceptURL, $declineURL);
    }
    
    /**
     * @return bool
     */
    public function preDispatchActions(): bool 
    {
        $hadCancels = Repo::invitation()
            ->cancelInvitationFamily($this->type, $this->email, $this->contextId, $this->reviewRoundId);

        return true;
    }
}
