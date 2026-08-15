<?php

namespace PKP\invitation\core\enums;

enum InvitationTypes : string
{
    case INVITATION_USER_ROLE_ASSIGNMENT = 'userRoleAssignment';
    case INVITATION_REVIEWER_ACCESS_INVITE = 'reviewerAccess';
}
