<?php

/**
 * @file classes/citation/enum/CitationSourceType.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationSourceType
 *
 * @ingroup citation
 *
 * @brief Enumeration for citation source types.
 */

namespace PKP\citation\enum;

enum CitationSourceType: string implements CitationBackedEnum
{
    case book_series = 'book series';
    case conference = 'conference';
    case ebook_platform = 'ebook platform';
    case journal = 'journal';
    case metadata = 'metadata';
    case other = 'other';
    case repository = 'repository';
}
