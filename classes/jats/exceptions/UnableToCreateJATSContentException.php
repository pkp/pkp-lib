<?php
/**
 * @file classes/jats/exceptions/UnableToCreateJATSContentException.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UnableToCreateJATSContentException
 *
 * @brief Exception for failure to create a Publication's JATS Content.
 */

namespace PKP\jats\exceptions;

use Exception;
use Throwable;

class UnableToCreateJATSContentException extends Exception
{
    public function __construct(public ?Throwable $innerException = null)
    {
        parent::__construct(
            __('publication.jats.defaultContentCreationError'), 
            $innerException?->getCode() ?? 0, 
            $innerException
        );
    }
}
