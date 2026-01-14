<?php

// Utility method to handle operations for requestion review response from authors

namespace PKP\submission\reviewRound;

use APP\core\Application;
use APP\core\Request;
use APP\decision\Decision;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\author\Author;
use PKP\context\Context;
use PKP\core\Core;
use PKP\decision\types\RequestRevisions;
use PKP\emailTemplate\EmailTemplate;
use PKP\mail\EmailData;
use PKP\mail\Mailable;
use PKP\mail\mailables\DecisionNotifyOtherAuthors;
use PKP\mail\mailables\DecisionRequestRevisionsNotifyAuthor;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\user\User;

class RequestAuthorReviewResponseManager
{
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

    public function getMailable()
    {
        // Need review assignments to prepare and use template for DecisionRequestRevisionsNotifyAuthor
        $reviewAssignments = $this->getCompletedReviewAssignments($this->submission->getId(), $this->reviewRound->getId(), RequestRevisions::REVIEW_ASSIGNMENT_COMPLETED);

        // The goal is to reuse the template associated with DecisionRequestRevisionsNotifyAuthor mailable when sending the request for author review response.
        // Instantiate the mailable with the necessary data, faking the Decision object.
        // A faked Decision will contain needed data to prepare the mailable, but the decision is not saved to the database.
        $fakeDecision = $this->getFakeDecision($this->submission, $this->request->getUser(), $this->reviewRound->getStageId(), $this->reviewRound);
        return new DecisionRequestRevisionsNotifyAuthor($this->context, $this->submission, $fakeDecision, $reviewAssignments);
    }

    /**
     * Create a fake decision object to be passed to the `DecisionRequestRevisionsNotifyAuthor` Mailable in order to
     * prepare data for email templates. The decision is not saved to the database.
     */
    private function getFakeDecision(Submission $submission, User $editor, int $stageId, ?ReviewRound $reviewRound = null): Decision
    {
        return Repo::decision()->newDataObject([
            'dateDecided' => Core::getCurrentDate(),
            'decision' => Decision::RECOMMEND_PENDING_REVISIONS,
            'editorId' => $editor->getId(),
            'reviewRoundId' => $reviewRound ? $reviewRound->getId() : null,
            'round' => $reviewRound ? $reviewRound->getRound() : null,
            'stageId' => $stageId,
            'submissionId' => $submission->getId(),
        ]);
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

        $assignedAuthorIds = StageAssignment::withSubmissionIds([$this->submission->getId()])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withStageIds([$this->reviewRound->getStageId()])
            ->get()
            ->pluck('user_id')
            ->all();

        $recipients = array_map(fn ($userId) => Repo::user()->get($userId), $assignedAuthorIds);

        $mailable
            ->sender($sender)
            ->bcc($emailData->bcc)
            ->cc($emailData->cc)
            ->subject($emailData->subject)
            ->body($emailData->body)
            ->recipients($recipients);

        if (!empty($emailData->attachments)) {
            $this->prepareAttachments($mailable, $emailData, $sender);
        }

        Mail::send($mailable);

        // Send email to contributing authors who are not assigned as participants
        if ($this->context->getData('notifyAllAuthors')) {
            /** @var Author[] $authors */
            $authors = $this->submission->getCurrentPublication()->getData('authors');
            $assignedAuthorEmails = array_map(fn (User $user) => $user->getEmail(), $recipients);
            $assignedAuthorIds = array_unique($assignedAuthorIds, SORT_NUMERIC);
            $assignedAuthors = Repo::user()->getCollector()->filterByUserIds($assignedAuthorIds)->getMany()->toArray();

            if ($authors) {
                $mailable = new DecisionNotifyOtherAuthors($this->context, $this->submission, $assignedAuthors);
                $emailTemplate = Repo::emailTemplate()->getByKey($this->context->getId(), $mailable::getEmailTemplateKey());

                $mailable
                    ->sender($sender)
                    ->subject($emailData->subject)
                    ->body($emailTemplate->getLocalizedData('body'))
                    ->addData([
                        $mailable::MESSAGE_TO_SUBMITTING_AUTHOR => $emailData->body,
                    ]);

                foreach ($authors as $author) {
                    if (!$author->getEmail() || in_array($author->getEmail(), $assignedAuthorEmails)) {
                        continue;
                    }

                    $mailable->recipients([$author]);
                    Mail::send($mailable);
                }
            }
        }
    }

    public function prepareAttachments(Mailable &$mailable, EmailData $emailData, User $sender): void
    {
        foreach ($emailData->attachments as $attachment) {
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

    public function getEmailTemplates(Mailable $mailable): array
    {
        $emailTemplates = collect();
        $request = Application::get()->getRequest();

        if ($mailable::getEmailTemplateKey()) {
            $emailTemplate = Repo::emailTemplate()->getByKey($this->context->getId(), $mailable::getEmailTemplateKey());
            if ($emailTemplate && Repo::emailTemplate()->isTemplateAccessibleToUser($request->getUser(), $emailTemplate, $this->context->getId())) {
                $emailTemplates->add($emailTemplate);
            }

            Repo::emailTemplate()
                ->getCollector($this->context->getId())
                ->alternateTo([$mailable::getEmailTemplateKey()])
                ->getMany()
                ->each(function (EmailTemplate $template) use ($request, $emailTemplates) {
                    if (Repo::emailTemplate()->isTemplateAccessibleToUser($request->getUser(), $template, $this->context->getId())) {
                        $emailTemplates->add($template);
                    }
                });
        }

        return Repo::emailTemplate()->getSchemaMap()->mapMany($emailTemplates)->all();
    }
}
