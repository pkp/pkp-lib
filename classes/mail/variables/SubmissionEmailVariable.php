<?php

/**
 * @file classes/mail/variables/SubmissionEmailVariable.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionEmailVariable
 *
 * @ingroup mail_variables
 *
 * @brief Represents variables associated with a submission that can be assigned to a template
 */

namespace PKP\mail\variables;

use APP\publication\Publication;
use APP\submission\Submission;
use PKP\author\Author;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\core\PKPString;
use PKP\mail\Mailable;

abstract class SubmissionEmailVariable extends Variable
{
    public const AUTHOR_SUBMISSION_URL = 'authorSubmissionUrl';
    public const AUTHORS = 'authors';
    public const AUTHORS_SHORT = 'authorsShort';
    public const SUBMISSION_ABSTRACT = 'submissionAbstract';
    public const SUBMISSION_ID = 'submissionId';
    public const SUBMISSION_PUBLISHED_URL = 'submissionPublishedUrl';
    public const SUBMISSION_TITLE = 'submissionTitle';
    public const SUBMISSION_URL = 'submissionUrl';
    public const SUBMISSION_WIZARD_URL = 'submissionWizardUrl';

    protected Submission $submission;
    protected Publication $currentPublication;

    public function __construct(Submission $submission, Mailable $mailable)
    {
        parent::__construct($mailable);

        $this->submission = $submission;
        $this->currentPublication = $this->submission->getCurrentPublication();
    }

    /**
     * @copydoc Variable::descriptions()
     */
    public static function descriptions(): array
    {
        return
        [
            self::AUTHOR_SUBMISSION_URL => __('emailTemplate.variable.submission.authorSubmissionUrl'),
            self::AUTHORS => __('emailTemplate.variable.submission.authors'),
            self::AUTHORS_SHORT => __('emailTemplate.variable.submission.authorsShort'),
            self::SUBMISSION_ABSTRACT => __('emailTemplate.variable.submission.submissionAbstract'),
            self::SUBMISSION_ID => __('emailTemplate.variable.submission.submissionId'),
            self::SUBMISSION_PUBLISHED_URL => __('emailTemplate.variable.submission.submissionPublishedUrl'),
            self::SUBMISSION_TITLE => __('emailTemplate.variable.submission.submissionTitle'),
            self::SUBMISSION_URL => __('emailTemplate.variable.submission.submissionUrl'),
            self::SUBMISSION_WIZARD_URL => __('emailTemplate.variable.submission.submissionWizardUrl'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    public function values(string $locale): array
    {
        $context = $this->getContext();
        return
        [
            self::AUTHOR_SUBMISSION_URL => $this->getAuthorSubmissionUrl($context),
            self::AUTHORS => htmlspecialchars($this->getAuthorsFull($locale)),
            self::AUTHORS_SHORT => htmlspecialchars($this->currentPublication->getShortAuthorString($locale)),
            self::SUBMISSION_ABSTRACT => PKPString::stripUnsafeHtml($this->currentPublication->getLocalizedData('abstract', $locale)),
            self::SUBMISSION_ID => (string) $this->submission->getId(),
            self::SUBMISSION_PUBLISHED_URL => $this->getSubmissionPublishedUrl($this->getContext()),
            self::SUBMISSION_TITLE => $this->currentPublication->getLocalizedFullTitle($locale, 'html'),
            self::SUBMISSION_URL => $this->getSubmissionUrl($context),
            self::SUBMISSION_WIZARD_URL => $this->getSubmissionWizardUrl($context),
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
     * URL to the author's submission workflow
     */
    protected function getAuthorSubmissionUrl(Context $context): string
    {
        $request = PKPApplication::get()->getRequest();
        return $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $context->getData('urlPath'),
            'dashboard',
            'mySubmissions',
            null,
            ['workflowSubmissionId' => $this->submission->getId()]
        );
    }

    /**
     * URL to a current workflow stage of the submission
     */
    protected function getSubmissionUrl(Context $context): string
    {
        $application = PKPApplication::get();
        $request = $application->getRequest();
        $dispatcher = $application->getDispatcher();
        return $dispatcher->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $context->getData('urlPath'),
            'dashboard',
            'editorial',
            null,
            ['workflowSubmissionId' => $this->submission->getId()]
        );
    }

    /**
     * URL to the submission in the submission wizard
     */
    protected function getSubmissionWizardUrl(Context $context): string
    {
        $application = PKPApplication::get();
        $request = $application->getRequest();
        $dispatcher = $application->getDispatcher();
        return $dispatcher->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $context->getPath(),
            'submission',
            null,
            null,
            [
                'id' => $this->submission->getId(),
            ]
        );
    }

    /**
     * URL to the published submission
     */
    abstract protected function getSubmissionPublishedUrl(Context $context): string;
}
