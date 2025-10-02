<?php

namespace PKP\invitation\core;

use PKP\invitation\core\enums\InvitationTypes;
use PKP\invitation\invitations\reviewerAccess\context\ReviewerAccessInvitationContext;
use PKP\invitation\invitations\userRoleAssignment\context\UserRoleAssignmentInvitationContext;

class InvitationContextFactory
{
    public static function make(string $invitationType): InvitationContext
    {
        return match ($invitationType) {
            InvitationTypes::INVITATION_USER_ROLE_ASSIGNMENT->value => new UserRoleAssignmentInvitationContext(),
            InvitationTypes::INVITATION_REVIEWER_ACCESS_INVITE->value => new ReviewerAccessInvitationContext(),
            default => throw new \InvalidArgumentException("Unknown invitation type: $invitationType"),
        };
    }
}
