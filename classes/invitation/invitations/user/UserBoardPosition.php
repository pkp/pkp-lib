<?php

/**
 * @file invitation/invitations/user/UserBoardPosition.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserBoardPosition
 *
 * @ingroup invitations
 *
 * @brief Board Position Assignment specific invitation
 */

namespace PKP\invitation\invitations\user;

use APP\facades\Repo;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use PKP\invitation\invitations\BaseInvitation;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\invitations\enums\InvitationType;
use PKP\mail\mailables\DispatchInvitation;

class UserBoardPosition extends BaseInvitation
{
    /**
     * The user ids to send email
     */
    protected int $positionId;

    protected ?int $invitedUserId;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $invitedUserId, string $email, int $contextId, int $positionId)
    {
        $this->positionId = $positionId;
        $this->invitedUserId = $invitedUserId;

        parent::__construct(InvitationType::NEW_BOARD_INVITATION_TYPE, $email, $contextId, $positionId);
    }


    /**
     * @return \PKP\mail\Mailable
     */
    public function getInvitationMailable(): Mailable 
    {
        $message = 'This is an invitation for assigning board positions'; // Should be localised
        $emailSubject = 'This is an invitation for assigning board positions'; // Should be localised
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
            ->cancelInvitationFamily($this->type, $this->email, $this->contextId, $this->positionId);

        return true;
    }
}
