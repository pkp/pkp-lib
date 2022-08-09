<?php
/**
 * @file classes/doi/exceptions/DoiActionException.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DoiActionException
 *
 * @brief Exception for failure to perform creation/edit actions on a DOI
 */

namespace PKP\doi\exceptions;

class DoiActionException extends \Exception
{
    public const PUBLICATION_MISSING_ISSUE = 'doi.submission.issueMissing.publication';
    public const REPRESENTATION_MISSING_ISSUE = 'doi.submission.issueMissing.representation';
    public const INCORRECT_SUBMISSION_CONTEXT = 'doi.submission.incorrectContext';
    public const INCORRECT_ISSUE_CONTEXT = 'doi.issue.incorrectContext';
    public const INCORRECT_STALE_STATUS = 'doi.incorrectStaleStatus';
    public const SUBMISSION_NOT_PUBLISHED = 'doi.submission.notPublished';
    public const ISSUE_NOT_PUBLISHED = 'doi.issue.notPublished';

    public function __construct(string $itemTitle, string $pubObjectTitle, string $error)
    {
        $errorMessage = __($error, ['itemTitle' => $itemTitle, 'pubObjectTitle' => $pubObjectTitle]);
        parent::__construct($errorMessage);
    }
}
