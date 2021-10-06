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

use PKP\author\Author;
use PKP\core\PKPApplication;
use PKP\i18n\PKPLocale;
use PKP\publication\PKPPublication;
use PKP\submission\PKPSubmission;

class SubmissionEmailVariable extends Variable
{
    public const SUBMISSION_TITLE = 'submissionTitle';
    public const SUBMISSION_ID = 'submissionId';
    public const SUBMISSION_ABSTRACT = 'submissionAbstract';
    public const AUTHORS = 'authors';
    public const AUTHORS_FULL = 'authorsFull';
    public const SUBMISSION_URL = 'submissionUrl';

    protected PKPSubmission $submission;

    protected PKPPublication $currentPublication;

    /**
     */
    public function __construct(PKPSubmission $submission)
    {
        $this->submission = $submission;
        // Submission's current publication should always be set
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
    protected function values(): array
    {
        return
        [
            self::SUBMISSION_TITLE => $this->getPublicationTitle(),
            self::SUBMISSION_ID => $this->getSubmissionId(),
            self::SUBMISSION_ABSTRACT => $this->getPublicationAbstract(),
            self::AUTHORS => $this->getAuthors(),
            self::AUTHORS_FULL => $this->getAuthorsFull(),
            self::SUBMISSION_URL => $this->getSubmissionUrl(),
        ];
    }

    protected function getPublicationTitle(): array
    {
        $fullTitlesLocalized = [];
        $supportedLocales = PKPLocale::getSupportedLocales();
        foreach ($supportedLocales as $localeKey => $localeValue) {
            $fullTitlesLocalized[$localeKey] = $this->currentPublication->getLocalizedFullTitle($localeKey);
        }
        return $fullTitlesLocalized;
    }

    protected function getSubmissionId(): int
    {
        return $this->submission->getId();
    }

    protected function getPublicationAbstract(): array
    {
        return $this->currentPublication->getData('abstract');
    }

    /**
     * Shortened authors string
     *
     * @see PKPPublication::getShortAuthorString()
     */
    protected function getAuthors(): array
    {
        $authorStringLocalized = [];
        $supportedLocales = PKPLocale::getSupportedLocales();
        foreach ($supportedLocales as $localeKey => $localeValue) {
            $authorStringLocalized[$localeKey] = $this->currentPublication->getShortAuthorString($localeKey);
        }

        return $authorStringLocalized;
    }

    /**
     * List of authors as a string separated by a comma
     */
    protected function getAuthorsFull(): array
    {
        $authorStringLocalized = [];
        $authors = $this->currentPublication->getData('authors');
        $supportedLocales = PKPLocale::getSupportedLocales();
        foreach ($supportedLocales as $localeKey => $localeValue) {
            $fullNames = array_map(function (Author $author) use ($localeKey) {
                return $author->getFullName(true, false, $localeKey);
            }, iterator_to_array($authors));

            $authorStringLocalized[$localeKey] = join(__('common.listSeparator'), $fullNames);
        }

        return $authorStringLocalized;
    }

    /**
     * URL to a current workflow stage of the submission
     */
    protected function getSubmissionUrl(): string
    {
        $request = PKPApplication::get()->getRequest();
        return $request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, null, 'workflow', 'index', [$this->submission->getId(), $this->submission->getData('stageId')]);
    }
}
