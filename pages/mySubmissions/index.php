<?php

/**
 * @defgroup pages_submissions Submissions editorial page
 */

/**
 * @file lib/pkp/pages/mySubmissions/index.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_submissions
 *
 * @brief Handle requests for submissions functions.
 *
 */

switch ($op) {
    case 'index':
        return new PKP\pages\dashboard\DashboardHandlerNext(PKP\pages\dashboard\DashboardPage::MY_SUBMISSIONS);
}
