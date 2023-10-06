<?php

/**
 * @defgroup pages_orcid ORCID Pages
 */

/**
 * @file pages/orcid/index.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_orcid
 *
 * @brief Handle requests for ORCID-related functions.
 *
 */

switch ($op) {
    case 'verify':
    case 'authorizeOrcid':
    case 'about':
        return new \PKP\pages\orcid\OrcidHandler();
}
