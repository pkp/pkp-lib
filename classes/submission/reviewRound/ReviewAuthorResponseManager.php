<?php

namespace PKP\submission\reviewRound;

use APP\core\Request;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\decision\types\traits\NotifyAuthors;
use PKP\mail\EmailData;
use PKP\mail\Mailable;
use PKP\mail\mailables\RequestReviewAuthorResponse;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\user\User;

class ReviewAuthorResponseManager
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

    public function getStageId(): int
    {
        return $this->reviewRound->getStageId();
    }

    public function getAssignedAuthorIds(Submission $submission): array
    {
        return StageAssignment::withSubmissionIds([$submission->getId()])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withStageIds([$this->getStageId()])
            ->get()
            ->pluck('user_id')
            ->all();
    }

    public function getMailable(): Mailable
    {
        return new RequestReviewAuthorResponse(
            $this->context,
            $this->submission,
            $this->getCompletedReviewAssignments(),
            $this->reviewRound
        );
    }

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

    public function sendMail(EmailData $emailData, User $sender): void
    {
        $mailable = $this->getMailable();
        $this->sendAuthorEmail($mailable, $emailData, $sender, $this->submission, $this->context);
    }

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
