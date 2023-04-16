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
 *
 * @ingroup core
 *
 * @brief Send an email to the authors when a preprint is posted
 */

namespace APP\observers\listeners;

use APP\author\Author;
use APP\facades\Repo;
use APP\mail\mailables\PostedAcknowledgement;
use APP\mail\mailables\PostedNewVersionAcknowledgement;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Mail;
use PKP\observers\events\PublicationPublished;

class SendPostedAcknowledgement
{
    /**
     * Maps methods with correspondent events to listen
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            PublicationPublished::class,
            SendPostedAcknowledgement::class
        );
    }

    public function handle(PublicationPublished $event)
    {
        if (!$event->context->getData('postedAcknowledgement')) {
            return;
        }

        $authors = $this->getAuthors($event);

        if (empty($authors)) {
            return;
        }

        $mailableClass = $event->publication->getData('version') == 1
            ? PostedAcknowledgement::class
            : PostedNewVersionAcknowledgement::class;

        $emailTemplate = Repo::emailTemplate()->getByKey(
            $event->submission->getData('contextId'),
            $mailableClass::getEmailTemplateKey()
        );

        $mailable = (new $mailableClass($event->context, $event->submission))
            ->from($event->context->getData('contactEmail'), $event->context->getData('contactName'))
            ->recipients($authors)
            ->subject($emailTemplate->getLocalizedData('subject'))
            ->body($emailTemplate->getLocalizedData('body'));

        Mail::send($mailable);
    }

    /**
     * @return Author[]
     */
    protected function getAuthors(PublicationPublished $event): array
    {
        $authorsWithEmail = [];

        foreach ($event->publication->getData('authors') as $author) {
            if ($author->getEmail()) {
                $authorsWithEmail[] = $author;
            }
        }

        return $authorsWithEmail;
    }
}
