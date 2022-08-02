<?php

/**
 * @file classes/mail/variables/SubmissionEmailVariable.php
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

use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\author\Author;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\security\Role;

class SubmissionEmailVariable extends Variable
{
    public const AUTHOR_SUBMISSION_URL = 'authorSubmissionUrl';
    public const AUTHORS = 'authors';
    public const AUTHORS_SHORT = 'authorsShort';
    public const SUBMISSION_ABSTRACT = 'submissionAbstract';
    public const SUBMITTING_AUTHOR_NAME = 'submittingAuthorName';
    public const SUBMISSION_ID = 'submissionId';
    public const SUBMISSION_TITLE = 'submissionTitle';
    public const SUBMISSION_URL = 'submissionUrl';

    protected Submission $submission;

    protected Publication $currentPublication;

    public function __construct(Submission $submission)
    {
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
            self::AUTHOR_SUBMISSION_URL => __('emailTemplate.variable.submission.authors'),
            self::AUTHORS => __('emailTemplate.variable.submission.authors'),
            self::AUTHORS_SHORT => __('emailTemplate.variable.submission.authorsShort'),
            self::SUBMISSION_ABSTRACT => __('emailTemplate.variable.submission.submissionAbstract'),
            self::SUBMITTING_AUTHOR_NAME => __('emailTemplate.variable.submission.submittingAuthorName'),
            self::SUBMISSION_ID => __('emailTemplate.variable.submission.submissionId'),
            self::SUBMISSION_TITLE => __('emailTemplate.variable.submission.submissionTitle'),
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
            self::AUTHOR_SUBMISSION_URL => $this->getAuthorSubmissionUrl(),
            self::AUTHORS => $this->getAuthorsFull($locale),
            self::AUTHORS_SHORT => $this->currentPublication->getShortAuthorString($locale),
            self::SUBMISSION_ABSTRACT => $this->currentPublication->getLocalizedData('abstract', $locale),
            self::SUBMITTING_AUTHOR_NAME => $this->getSubmittingAuthorName($locale),
            self::SUBMISSION_ID => (string) $this->submission->getId(),
            self::SUBMISSION_TITLE => $this->currentPublication->getLocalizedFullTitle($locale),
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
     * URL to the author's submission workflow
     */
    protected function getAuthorSubmissionUrl(): string
    {
        $request = PKPApplication::get()->getRequest();
        return $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'authorDashboard',
            'submission',
            [
                $this->submission->getId(),
            ]
        );
    }

    /**
     * URL to a current workflow stage of the submission
     */
    protected function getSubmissionUrl(): string
    {
        $application = PKPApplication::get();
        $request = $application->getRequest();
        $dispatcher = $application->getDispatcher();
        return $dispatcher->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'workflow',
            'index',
            [
                $this->submission->getId(),
                $this->submission->getData('stageId'),
            ]
        );
    }

    /**
     * The name(s) of authors assigned as participants to the
     * submission workflow.
     *
     * Usually this is the submitting author.
     */
    protected function getSubmittingAuthorName(string $locale): string
    {
        $authorNames = [];
        $alreadyCollected = []; // Prevent duplicate names for each stage assignment
        /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $result = $stageAssignmentDao->getBySubmissionAndRoleId($this->submission->getId(), Role::ROLE_ID_AUTHOR);
        /** @var StageAssignment $stageAssignment */
        while ($stageAssignment = $result->next()) {
            $userId = (int) $stageAssignment->getUserId();
            if (in_array($userId, $alreadyCollected)) {
                continue;
            }
            $alreadyCollected[] = $userId;
            $user = Repo::user()->get($userId);
            if ($user) {
                $authorNames[] = $user->getFullName(true, false, $locale);
            }
        }
        return join(__('common.commaListSeparator'), $authorNames);
    }
}
