<?php

/**
 * @file classes/publication/enums/VersionRelationType.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VersionRelationType
 *
 * @brief Enumeration describing how one published version relates to another.
 *
 * The case values are the DataCite Metadata Schema relationType terms (also used
 * by OpenAIRE and emitted by the Crossref export), so they can be carried through
 * to metadata outputs unchanged.
 */

namespace PKP\publication\enums;

enum VersionRelationType: string
{
    case IS_NEW_VERSION_OF = 'isNewVersionOf';
    case IS_PREVIOUS_VERSION_OF = 'isPreviousVersionOf';
    case IS_VERSION_OF = 'isVersionOf';
}
