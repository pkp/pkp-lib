<?php

/**
 * @defgroup pages_about About page
 */

/**
 * @file pages/about/index.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_about
 *
 * @brief Handle requests for about the context functions.
 *
 */

switch ($op) {
    case 'index':
    case 'editorialMasthead':
    case 'editorialHistory':
    case 'submissions':
    case 'contact':
        return new \PKP\pages\about\AboutContextHandler();
    case 'privacy':
    case 'aboutThisPublishingSystem':
        return new \PKP\pages\about\AboutSiteHandler();
}
