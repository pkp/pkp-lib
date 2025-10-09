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
    case Book = 'book';
    case BookChapter = 'book-chapter';
    case BookPart = 'book-part';
    case BookSection = 'book-section';
    case BookSeries = 'book-series';
    case BookSet = 'book-set';
    case BookTrack = 'book-track';
    case Component = 'component';
    case Database = 'database';
    case Dataset = 'dataset';
    case Dissertation = 'dissertation';
    case EditedBook = 'edited-book';
    case Editorial = 'editorial';
    case Erratum = 'erratum';
    case Grant = 'grant';
    case Journal = 'journal';
    case JournalArticle = 'journal-article';
    case JournalIssue = 'journal-issue';
    case JournalVolume = 'journal-volume';
    case Letter = 'letter';
    case LibGuides = 'libguides';
    case Monograph = 'monograph';
    case Other = 'other';
    case Paratext = 'paratext';
    case PeerReview = 'peer-review';
    case PostedContent = 'posted-content';
    case Preprint = 'preprint';
    case Proceedings = 'proceedings';
    case ProceedingsArticle = 'proceedings-article';
    case ProceedingsSeries = 'proceedings-series';
    case ReferenceBook = 'reference-book';
    case ReferenceEntry = 'reference-entry';
    case Report = 'report';
    case ReportComponent = 'report-component';
    case ReportSeries = 'report-series';
    case Retraction = 'retraction';
    case Review = 'review';
    case Standard = 'standard';
    case SupplementaryMaterials = 'supplementary-materials';
}
