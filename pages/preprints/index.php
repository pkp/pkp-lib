<?php

/**
 * @defgroup pages_preprint Preprints archive page
 */

/**
 * @file pages/preprints/index.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_preprints
 * @brief Handle requests for preprints archive view.
 *
 */

switch ($op) {
    case 'index':
        define('HANDLER_CLASS', 'PreprintsHandler');
        import('pages.preprints.PreprintsHandler');
        break;
    case 'category':
    case 'fullSize':
    case 'thumbnail':
        define('HANDLER_CLASS', 'PKPCatalogHandler');
        import('lib.pkp.pages.catalog.PKPCatalogHandler');
        break;
    case 'section':
        define('HANDLER_CLASS', 'SectionsHandler');
        import('pages.preprints.SectionsHandler');
        break;
}
