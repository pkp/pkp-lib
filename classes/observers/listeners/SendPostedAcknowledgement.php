<?php

declare(strict_types=1);

/**
 * @file classes/observers/listeners/SendPostedAcknowledgement.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SendPostedAcknowledgement
 * @ingroup core
 *
 * @brief Send an email to the authors when a preprint is posted
 */

namespace APP\observers\listeners;

use APP\core\Services;
use APP\facades\Repo;
use APP\mail\mailables\PostedAcknowledgement;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Mail;
use PKP\observers\events\PublishedEvent;

class SendPostedAcknowledgement
{
    /**
     * Maps methods with correspondent events to listen
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            PublishedEvent::class,
            self::class . '@handle'
        );
    }

    public function handle(PublishedEvent $event)
    {
        // Only send this email when the first version is published
        if ($event->newPublication->getData('version') !== 1) {
            return;
        }

        $context = Services::get('context')->get($event->submission->getData('contextId'));

        if (!$context->getData('postedAcknowledgement')) {
            return;
        }

        $submission = Repo::submission()->get($event->newPublication->getData('submissionId'));

        $mailable = new PostedAcknowledgement($context, $submission);
        $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), PostedAcknowledgement::getEmailTemplateKey());

        $assignedAuthors = Repo::author()->getSubmissionAuthors($submission);

        $mailable
            ->from($context->getData('contactEmail'), $context->getData('contactName'))
            ->recipients($assignedAuthors->toArray())
            ->subject($emailTemplate->getLocalizedData('subject'))
            ->body($emailTemplate->getLocalizedData('body'));

        Mail::send($mailable);
    }
}
