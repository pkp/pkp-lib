<?php

/**
 * @defgroup pages_help Help Pages
 */

/**
 * @file lib/pkp/pages/help/index.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_help
 * @brief Handle requests for help functions.
 *
 */
$op = 'index';
define('HANDLER_CLASS', 'HelpHandler');
import('lib.pkp.pages.help.HelpHandler');
