<?php

/**
 * @file invitation/core/enums/InvitationStatus.php
 *
 * Copyright (c) 2023-2024 Simon Fraser University
 * Copyright (c) 2023-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationStatus
 *
 * @brief Enumeration for invitation statuses
 */

namespace PKP\invitation\core\enums;

enum ValidationContext: string
{
    case VALIDATION_CONTEXT_DEFAULT = 'default';
    case VALIDATION_CONTEXT_INVITE = 'invite';
    case VALIDATION_CONTEXT_FINALIZE = 'finalize';
    case VALIDATION_CONTEXT_POPULATE = 'populate';
    case VALIDATION_CONTEXT_REFINE = 'refine';
}
