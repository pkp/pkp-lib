<?php

/**
 * @file classes/citation/job/pid/Url.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Url
 *
 * @ingroup citation
 *
 * @brief Url class
 */

namespace PKP\citation\job\pid;

class Url extends BasePid
{
    /** @copydoc AbstractPid::regex */
    public const regex = '%\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))%s';
}
