<?php
/**
 * @file classes/doi/exceptions/DoiCreationException.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DoiCreationException
 *
 * @brief Exception for failure to create a valid DOI
 */

namespace PKP\doi\exceptions;

use APP\issue\Issue;
use APP\submission\Submission;
use PKP\core\DataObject;

class DoiCreationException extends \Exception
{
    public const PUBLICATION_MISSING_ISSUE = 'doi.submission.issueMissing.publication';
    public const REPRESENTATION_MISSING_ISSUE = 'doi.submission.issueMissing.representation';
    public const INCORRECT_SUBMISSION_CONTEXT = 'doi.submission.incorrectContext';
    public const INCORRECT_ISSUE_CONTEXT = 'doi.issue.incorrectContext';

    public function __construct(string $itemTitle, string $pubObjectTitle, string $error)
    {
        $errorMessage = __($error, ['itemTitle' => $itemTitle, 'pubObjectTitle' => $pubObjectTitle]);
        parent::__construct($errorMessage);
    }
}
