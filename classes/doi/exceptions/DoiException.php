<?php
/**
 * @file classes/doi/exceptions/DoiException.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DoiException
 *
 * @brief Exception for failure to perform any action on a DOI
 */

namespace PKP\doi\exceptions;

class DoiException extends \Exception
{
    public const PUBLICATION_MISSING_ISSUE = 'doi.submission.issueMissing.publication';
    public const REPRESENTATION_MISSING_ISSUE = 'doi.submission.issueMissing.representation';
    public const INCORRECT_SUBMISSION_CONTEXT = 'doi.submission.incorrectContext';
    public const INCORRECT_ISSUE_CONTEXT = 'doi.issue.incorrectContext';
    public const INCORRECT_STALE_STATUS = 'doi.incorrectStaleStatus';
    public const SUBMISSION_NOT_PUBLISHED = 'doi.submission.notPublished';
    public const ISSUE_NOT_PUBLISHED = 'doi.issue.notPublished';

    /**
     * @param string $errorKey Locale key for message to send in exception
     * @param string|null $itemTitle Top-level object title (submission or issue)
     * @param string|null $pubObjectTitle Publication object title (publication, galley, chapter, etc.)
     */
    public function __construct(string $errorKey, ?string $itemTitle = null, ?string $pubObjectTitle = null)
    {
        $errorMessage = __($errorKey, ['itemTitle' => $itemTitle, 'pubObjectTitle' => $pubObjectTitle]);
        parent::__construct($errorMessage);
    }
}
