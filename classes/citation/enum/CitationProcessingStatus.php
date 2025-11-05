<?php

/**
 * @file classes/citation/enum/CitationProcessingStatus.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationProcessingStatus
 *
 * @ingroup citation
 *
 * @brief Enumeration for citation processing status.
 */

namespace PKP\citation\enum;

enum CitationProcessingStatus: int
{
    case NOT_PROCESSED = 0;
    case PID_EXTRACTED = 1;
    case CROSSREF = 2;
    case OPEN_ALEX = 3;
    case ORCID = 4;
    case PROCESSED = 5;
}
