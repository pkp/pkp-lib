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

enum CitationType: string
{
    case BOOK = 'book';
    case BOOK_CHAPTER = 'book-chapter';
    case BOOK_PART = 'book-part';
    case BOOK_SECTION = 'book-section';
    case BOOK_SERIES = 'book-series';
    case BOOK_SET = 'book-set';
    case BOOK_TRACK = 'book-track';
    case COMPONENT = 'component';
    case DATABASE = 'database';
    case DATASET = 'dataset';
    case DISSERTATION = 'dissertation';
    case EDITED_BOOK = 'edited-book';
    case EDITORIAL = 'editorial';
    case ERRATUM = 'erratum';
    case GRANT = 'grant';
    case JOURNAL = 'journal';
    case JOURNAL_ARTICLE = 'journal-article';
    case JOURNAL_ISSUE = 'journal-issue';
    case JOURNAL_VOLUME = 'journal-volume';
    case LETTER = 'letter';
    case LIBGUIDES = 'libguides';
    case MONOGRAPH = 'monograph';
    case OTHER = 'other';
    case PARATEXT = 'paratext';
    case PEER_REVIEW = 'peer-review';
    case POSTED_CONTENT = 'posted-content';
    case PREPRINT = 'preprint';
    case PROCEEDINGS = 'proceedings';
    case PROCEEDINGS_ARTICLE = 'proceedings-article';
    case PROCEEDINGS_SERIES = 'proceedings-series';
    case REFERENCE_BOOK = 'reference-book';
    case REFERENCE_ENTRY = 'reference-entry';
    case REPORT = 'report';
    case REPORT_COMPONENT = 'report-component';
    case REPORT_SERIES = 'report-series';
    case RETRACTION = 'retraction';
    case REVIEW = 'review';
    case STANDARD = 'standard';
    case SUPPLEMENTARY_MATERIALS = 'supplementary-materials';
}
