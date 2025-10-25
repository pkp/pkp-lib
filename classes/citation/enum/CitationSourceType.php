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

enum CitationSourceType: string
{
    case BOOK_SERIES = 'book series';
    case CONFERENCE = 'conference';
    case EBOOK_PLATFORM = 'ebook platform';
    case JOURNAL = 'journal';
    case METADATA = 'metadata';
    case OTHER = 'other';
    case REPOSITORY = 'repository';
}
