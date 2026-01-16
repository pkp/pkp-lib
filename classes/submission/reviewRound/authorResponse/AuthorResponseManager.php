<?php

/**
 * @file classes/submission/reviewRound/authorResponse/AuthorResponseManager.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorResponseManager
 *
 * @brief class with methods to manage author responses to review rounds
 */

namespace PKP\submission\reviewRound\authorResponse;

use APP\core\Request;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\mail\EmailData;
use PKP\mail\Mailable;
use PKP\mail\mailables\RequestReviewRoundAuthorResponse;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewRound\ReviewRound;
use PKP\user\User;

class AuthorResponseManager
{
    use NotifyAuthors;

    private ReviewRound $reviewRound;
    private Submission $submission;
    private Context $context;
    private array $locales;
    private Request $request;

    public function __construct(ReviewRound $reviewRound, Submission $submission, Context $context, Request $request)
    {
        $this->reviewRound = $reviewRound;
        $this->submission = $submission;
        $this->context = $context;
        $this->locales = $context->getSupportedLocales();
        $this->request = $request;
    }

    /** @inheritDoc */
    public function getStageId(): int
    {
        return $this->reviewRound->getStageId();
    }

    /** @inheritDoc */
    public function getAssignedAuthorIds(Submission $submission): array
    {
        return StageAssignment::withSubmissionIds([$submission->getId()])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withStageIds([$this->getStageId()])
            ->get()
            ->pluck('user_id')
            ->all();
    }

    /**
     * Get the mailable to use.
     */
    public function getMailable(): Mailable
    {
        return new RequestReviewRoundAuthorResponse(
            $this->context,
            $this->submission,
            $this->getCompletedReviewAssignments(),
            $this->reviewRound
        );
    }

    /**
     * Get completed review assignments.
     */
    private function getCompletedReviewAssignments(): array
    {
        return Repo::reviewAssignment()->getCollector()
            ->filterByReviewRoundIds([$this->reviewRound->getId()])
            ->filterBySubmissionIds([$this->submission->getId()])
            ->filterByStageId($this->reviewRound->getStageId())
            ->filterByCompleted(true)
            ->getMany()
            ->toArray();
    }


    /**
     * Send author request email.
     */
    public function sendAuthorRequest(EmailData $emailData, User $sender): void
    {
        $mailable = $this->getMailable();
        $this->sendAuthorEmail($mailable, $emailData, $sender, $this->submission, $this->context);
    }

    /**
     * Populate mailable with email data.
     */
    protected function addEmailDataToMailable(Mailable $mailable, User $sender, EmailData $email): Mailable
    {
        $mailable
            ->sender($sender)
            ->bcc($email->bcc)
            ->cc($email->cc)
            ->subject($email->subject)
            ->body($email->body);

        if (!empty($email->attachments)) {
            foreach ($email->attachments as $attachment) {
                if (isset($attachment[Mailable::ATTACHMENT_TEMPORARY_FILE])) {
                    $mailable->attachTemporaryFile(
                        $attachment[Mailable::ATTACHMENT_TEMPORARY_FILE],
                        $attachment['name'],
                        $sender->getId()
                    );
                } elseif (isset($attachment[Mailable::ATTACHMENT_SUBMISSION_FILE])) {
                    $mailable->attachSubmissionFile(
                        $attachment[Mailable::ATTACHMENT_SUBMISSION_FILE],
                        $attachment['name']
                    );
                } elseif (isset($attachment[Mailable::ATTACHMENT_LIBRARY_FILE])) {
                    $mailable->attachLibraryFile(
                        $attachment[Mailable::ATTACHMENT_LIBRARY_FILE],
                        $attachment['name']
                    );
                }
            }
        }

        return $mailable;
    }
}
