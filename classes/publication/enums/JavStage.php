<?php

/**
 * @file publication/enums/JavStage.php
 *
 * Copyright (c) 2023-2024 Simon Fraser University
 * Copyright (c) 2023-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JavStage
 *
 * @brief Enumeration for JAV versioning stages
 */

namespace PKP\publication\enums;

enum JavStage: string
{
    case AUTHOR_ORIGINAL = 'AO';
    case ACCEPTED_MANUSCRIPT = 'AM';
    case SUBMITTED_MANUSCRIPT = 'SM';
    case PROOF = 'PF';
    case VERSION_OF_RECORD = 'VoR';
}
