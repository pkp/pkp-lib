<?php

/**
 * @file classes/author/creditRoles/CreditRoleDegree.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CreditRoleDegree
 *
 * @brief Enum class to define degrees of contribution
 */

namespace PKP\author\creditRoles;

enum CreditRoleDegree: string
{
    case NO_DEGREE = 'NO_DEGREE';
    case LEAD = 'LEAD';
    case EQUAL = 'EQUAL';
    case SUPPORTING = 'SUPPORTING';
}