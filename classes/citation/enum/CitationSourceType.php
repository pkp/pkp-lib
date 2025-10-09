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
    case BookSeries = 'book series';
    case Conference = 'conference';
    case EbookPlatform = 'ebook platform';
    case Journal = 'journal';
    case Metadata = 'metadata';
    case Other = 'other';
    case Repository = 'repository';
}
