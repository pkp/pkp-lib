<?php

/**
 * @file classes/mail/variables/SubmissionEmailVariable.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionEmailVariable
 * @ingroup mail_variables
 *
 * @brief Represents variables associated with a submission that can be assigned to a template
 */

namespace PKP\mail\variables;

use APP\publication\Publication;
use APP\submission\Submission;
use PKP\author\Author;
use PKP\core\PKPApplication;

class SubmissionEmailVariable extends Variable
{
    public const SUBMISSION_TITLE = 'submissionTitle';
    public const SUBMISSION_ID = 'submissionId';
    public const SUBMISSION_ABSTRACT = 'submissionAbstract';
    public const AUTHORS = 'authors';
    public const AUTHORS_FULL = 'authorsFull';
    public const SUBMISSION_URL = 'submissionUrl';

    protected Submission $submission;

    protected Publication $currentPublication;

    /**
     */
    public function __construct(Submission $submission)
    {
        $this->submission = $submission;
        $this->currentPublication = $this->submission->getCurrentPublication();
    }

    /**
     * @copydoc Variable::description()
     */
    protected static function description(): array
    {
        return
        [
            self::SUBMISSION_TITLE => __('emailTemplate.variable.submission.submissionTitle'),
            self::SUBMISSION_ID => __('emailTemplate.variable.submission.submissionId'),
            self::SUBMISSION_ABSTRACT => __('emailTemplate.variable.submission.submissionAbstract'),
            self::AUTHORS => __('emailTemplate.variable.submission.authors'),
            self::AUTHORS_FULL => __('emailTemplate.variable.submission.authorsFull'),
            self::SUBMISSION_URL => __('emailTemplate.variable.submission.submissionUrl'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    public function values(string $locale): array
    {
        return
        [
            self::SUBMISSION_TITLE => $this->currentPublication->getLocalizedFullTitle($locale),
            self::SUBMISSION_ID => $this->submission->getId(),
            self::SUBMISSION_ABSTRACT => $this->currentPublication->getLocalizedData('abstract', $locale),
            self::AUTHORS => $this->currentPublication->getShortAuthorString($locale),
            self::AUTHORS_FULL => $this->getAuthorsFull($locale),
            self::SUBMISSION_URL => $this->getSubmissionUrl(),
        ];
    }

    /**
     * List of authors as a string separated by a comma
     */
    protected function getAuthorsFull(string $locale): string
    {
        $authors = $this->currentPublication->getData('authors');
        $fullNames = array_map(function (Author $author) use ($locale) {
            return $author->getFullName(true, false, $locale);
        }, iterator_to_array($authors));

         return join(__('common.commaListSeparator'), $fullNames);
    }

    /**
     * URL to a current workflow stage of the submission
     */
    protected function getSubmissionUrl(): string
    {
        $request = PKPApplication::get()->getRequest();
        return $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'workflow',
            'index',
            [$this->submission->getId(),
            $this->submission->getData('stageId')]
        );
    }
}
