<?php
/**
 * @file classes/section/Repository.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage sections.
 */

namespace APP\section;

class Repository extends \PKP\section\Repository
{
    public string $schemaMap = maps\Schema::class;
}
