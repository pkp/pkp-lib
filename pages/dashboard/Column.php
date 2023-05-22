<?php
/**
 * @file pages/dashboard/Column.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Column
 *
 * @ingroup pages_dashboard
 *
 * @brief A class that represents a column in the submissions table
 */

namespace PKP\pages\dashboard;

class Column
{
    public function __construct(
        public string $id,
        public string $header,
        public string $template,
        public bool $sortable = false,
    ) {
        //
    }
}