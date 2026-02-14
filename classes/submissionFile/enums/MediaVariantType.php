<?php

/**
 * @file classes/submissionFile/enums/MediaVariantType.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MediaVariantType
 *
 * @brief Enum class to define available media variant types
 */

namespace PKP\submissionFile\enums;

enum MediaVariantType: string
{
    case WEB = 'web';
    case HIGH_RESOLUTION = 'high_resolution';
}
