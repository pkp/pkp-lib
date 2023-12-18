<?php
/**
 * @file classes/jats/exceptions/UnableToCreateFileContentException.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UnableToCreateFileContentException
 *
 * @brief Exception for failure to create a Submission File's content
 */

namespace PKP\submissionFile\exceptions;

use Exception;

class UnableToCreateFileContentException extends Exception
{

    public function __construct(string $fileName, public ?Exception $innerException = null)
    {
        parent::__construct(__('submission.files.content.error', ['fileName' => $fileName]), null, $innerException);
    }
}
