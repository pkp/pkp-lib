<?php

/**
 * @file classes/citation/enum/CitationType.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationType
 *
 * @ingroup citation
 *
 * @brief Enumeration for citation types.
 */

namespace PKP\citation\enum;

enum CitationType: string implements CitationBackedEnum
{
    case book = 'book';
    case book_chapter = 'book-chapter';
    case book_part = 'book-part';
    case book_section = 'book-section';
    case book_series = 'book-series';
    case book_set = 'book-set';
    case book_track = 'book-track';
    case component = 'component';
    case database = 'database';
    case dataset = 'dataset';
    case dissertation = 'dissertation';
    case edited_book = 'edited-book';
    case editorial = 'editorial';
    case erratum = 'erratum';
    case grant = 'grant';
    case journal = 'journal';
    case journal_article = 'journal-article';
    case journal_issue = 'journal-issue';
    case journal_volume = 'journal-volume';
    case letter = 'letter';
    case libguides = 'libguides';
    case monograph = 'monograph';
    case other = 'other';
    case paratext = 'paratext';
    case peer_review = 'peer-review';
    case posted_content = 'posted-content';
    case preprint = 'preprint';
    case proceedings = 'proceedings';
    case proceedings_article = 'proceedings-article';
    case proceedings_series = 'proceedings-series';
    case reference_book = 'reference-book';
    case reference_entry = 'reference-entry';
    case report = 'report';
    case report_component = 'report-component';
    case report_series = 'report-series';
    case retraction = 'retraction';
    case review = 'review';
    case standard = 'standard';
    case supplementary_materials = 'supplementary-materials';
}
