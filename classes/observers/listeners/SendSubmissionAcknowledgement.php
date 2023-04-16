<?php
/**
 * @file classes/observers/listeners/SendSubmissionAcknowledgement.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SendSubmissionAcknowledgement
 *
 * @ingroup observers_listeners
 *
 * @brief Send an email acknowledgement to the submitting author when a new submission is submitted
 *
 * Sends an email to all users with author stage assignments and
 * sends a separate email to all other contributors named on the
 * submission.
 */

namespace APP\observers\listeners;

use APP\facades\Repo;
use APP\mail\mailables\SubmissionAcknowledgementCanPost;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Enumerable;
use PKP\mail\Mailable;
use PKP\mail\mailables\SubmissionAcknowledgement;
use PKP\observers\events\SubmissionSubmitted;
use PKP\user\User;

class SendSubmissionAcknowledgement extends \PKP\observers\listeners\SendSubmissionAcknowledgement
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            SubmissionSubmitted::class,
            SendSubmissionAcknowledgement::class
        );
    }

    /**
     * Get a different mailable depending on whether the authors
     * can publish their own preprint
     *
     * All submitting authors must be able to publish or else the
     * moderated
     */
    protected function getSubmitterMailable(SubmissionSubmitted $event, Enumerable $submitterUsers): Mailable
    {
        $canPublish = $submitterUsers
            ->filter(fn (User $user) => !Repo::publication()->canCurrentUserPublish($event->submission->getId(), $user))
            ->isEmpty();

        $mailableClass = $canPublish
            ? SubmissionAcknowledgementCanPost::class
            : SubmissionAcknowledgement::class;

        $emailTemplate = Repo::emailTemplate()->getByKey(
            $event->context->getId(),
            $mailableClass::getEmailTemplateKey()
        );

        return (new $mailableClass($event->context, $event->submission))
            ->from($event->context->getData('contactEmail'), $event->context->getData('contactName'))
            ->recipients($submitterUsers->toArray())
            ->subject($emailTemplate->getLocalizedData('subject'))
            ->body($emailTemplate->getLocalizedData('body'));
    }
}
