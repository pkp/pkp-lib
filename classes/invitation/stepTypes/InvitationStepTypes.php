<?php
/**
 * @file classes/invitation/stepType/InvitationStepTypes.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationStepTypes
 *
 * @brief A base class to define a step types in an invitation.
 */
namespace PKP\invitation\stepTypes;

use PKP\context\Context;
use PKP\invitation\core\Invitation;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;
use PKP\user\User;

abstract class InvitationStepTypes
{
    public const INVITATION_USER_ROLE_ASSIGNMENT = 'userRoleAssignment';
    public const INVITATION_REVIEWER_ACCESS_INVITE = 'reviewerAccess';
    /**
     * Get the invitation steps
     * use of the built-in UI for making the invitation
     */
    abstract public function getSteps(?Invitation $invitation, Context $context, ?User $user, string $invitationType): array;

    /** fake invitation for email template
     */
    protected function getFakeInvitation(string $invitationType): UserRoleAssignmentInvite | ReviewerAccessInvite
    {
        if (self::INVITATION_USER_ROLE_ASSIGNMENT === $invitationType) {
            return new UserRoleAssignmentInvite();
        }
        return new ReviewerAccessInvite();
    }


}
