<?php

/**
 * @file classes/citation/job/pid/Urn.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Urn
 *
 * @ingroup citation
 *
 * @brief Urn class
 */

namespace PKP\citation\job\pid;

class Urn extends BasePid
{
    /** @copydoc AbstractPid::regex */
    public const regex = '/urn:([a-z0-9][a-z0-9-]{1,31}):((?:[-a-z0-9()+,.:=@;$_!*\'&~\/]|%[0-9a-f]{2})+)(?:(\?\+)((?:(?!\?=)(?:[-a-z0-9()+,.:=@;$_!*\'&~\/\?]|%[0-9a-f]{2}))*))?(?:(\?=)((?:(?!#).)*))?(?:(#)((?:[-a-z0-9()+,.:=@;$_!*\'&~\/\?]|%[0-9a-f]{2})*))?$/i';
}
