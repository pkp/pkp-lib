<?php

/**
 * @file classes/orcid/enums/OrcidDepositType.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @enum OrcidDepositType
 *
 * @brief Possible contribution deposit types for ORCID service.
 */

namespace PKP\orcid\enums;

enum OrcidDepositType: string
{
    case WORK = 'work';
    case REVIEW = 'review';
}
