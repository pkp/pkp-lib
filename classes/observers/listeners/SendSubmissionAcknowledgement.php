<?php
/**
 * @file classes/observers/listeners/SendSubmissionAcknowledgement.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SendSubmissionAcknowledgement
 * @ingroup observers_listeners
 *
 * @brief Send an email acknowledgement to the submitting author when a new submission is submitted
 *
 * Sends an email to all users with author stage assignments and
 * sends a separate email to all other contributors named on the
 * submission.
 */

namespace PKP\observers\listeners;

use APP\author\Author;
use APP\facades\Repo;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Mail;
use PKP\db\DAORegistry;
use PKP\log\SubmissionEmailLogEntry;
use PKP\mail\Mailable;
use PKP\mail\mailables\SubmissionAcknowledgement;
use PKP\mail\mailables\SubmissionAcknowledgementOtherAuthors;
use PKP\observers\events\SubmissionSubmitted;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignmentDAO;
use PKP\user\User;

abstract class SendSubmissionAcknowledgement
{
    abstract public function subscribe(Dispatcher $events): void;

    public function handle(SubmissionSubmitted $event)
    {
        /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $result = $stageAssignmentDao->getBySubmissionAndRoleIds($event->submission->getId(), [Role::ROLE_ID_AUTHOR]);
        $assignedUserIds = [];
        while ($stageAssignment = $result->next()) {
            /** @var StageAssignment $stageAssignment */
            $assignedUserIds[] = $stageAssignment->getUserId();
        }

        $submitterUsers = Repo::user()
            ->getCollector()
            ->filterByUserIds($assignedUserIds)
            ->getMany();

        if ($submitterUsers->count()) {
            $mailable = $this->getSubmitterMailable($event, $submitterUsers);

            if ($event->context->getData('copySubmissionAckPrimaryContact')) {
                $mailable->bcc($event->context->getData('contactEmail'), $event->context->getData('contactName'));
            }

            if (!empty($event->context->getData('copySubmissionAckAddress'))) {
                $emails = explode(',', trim($event->context->getData('copySubmissionAckAddress')));
                $mailable->bcc($emails);
            }

            Mail::send($mailable);

            /** @var SubmissionEmailLogDAO $logDao */
            $logDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
            $logDao->logMailable(
                SubmissionEmailLogEntry::SUBMISSION_EMAIL_AUTHOR_SUBMISSION_ACK,
                $mailable,
                $event->submission
            );
        }

        $submitterEmails = $submitterUsers->map(fn (User $user) => $user->getEmail());

        $otherAuthors = $event->submission
            ->getCurrentPublication()
            ->getData('authors')
            ->filter(fn (Author $author) => !$submitterEmails->contains($author->getEmail()));

        if ($otherAuthors->count()) {
            $emailTemplate = Repo::emailTemplate()->getByKey(
                $event->context->getId(),
                SubmissionAcknowledgementOtherAuthors::getEmailTemplateKey()
            );

            $mailable = new SubmissionAcknowledgementOtherAuthors($event->context, $event->submission, $submitterUsers);
            $mailable
                ->from($event->context->getData('contactEmail'), $event->context->getData('contactName'))
                ->recipients($otherAuthors->toArray())
                ->subject($emailTemplate->getLocalizedData('subject'))
                ->body($emailTemplate->getLocalizedData('body'));

            Mail::send($mailable);

            /** @var SubmissionEmailLogDAO $logDao */
            $logDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
            $logDao->logMailable(
                SubmissionEmailLogEntry::SUBMISSION_EMAIL_AUTHOR_SUBMISSION_ACK,
                $mailable,
                $event->submission
            );
        }
    }

    protected function getSubmitterMailable(SubmissionSubmitted $event, Enumerable $submitterUsers): Mailable
    {
        $emailTemplate = Repo::emailTemplate()->getByKey(
            $event->context->getId(),
            SubmissionAcknowledgement::getEmailTemplateKey()
        );

        return (new SubmissionAcknowledgement($event->context, $event->submission))
            ->from($event->context->getData('contactEmail'), $event->context->getData('contactName'))
            ->recipients($submitterUsers->toArray())
            ->subject($emailTemplate->getLocalizedData('subject'))
            ->body($emailTemplate->getLocalizedData('body'));
    }
}
