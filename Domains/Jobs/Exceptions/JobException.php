<?php

declare(strict_types=1);

/**
 * @file Domains/Jobs/Exceptions/JobException.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JobException
 * @ingroup domains
 *
 * @brief Exception for Job domain
 */

namespace PKP\Domains\Jobs\Exceptions;

use Exception;

class JobException extends Exception
{
    public const INVALID_PAYLOAD = 'invalid.job.payload';

    /**
     * Customize the class getMessage()
     */
    public function __toString(): string
    {
        return self::class .
                ": [{$this->getCode()}] in " .
                "{$this->getFile()} ({$this->getLine()}): " .
                "{$this->getMessage()}\n";
    }
}
