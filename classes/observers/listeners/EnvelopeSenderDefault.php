<?php

/**
 * @file classes/observers/listeners/EnvelopeSenderDefault.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EnvelopeSenderDefault
 * @ingroup observers_listeners
 *
 * @brief Sets the envelope sender if it's specified in the config
 */

namespace PKP\observers\listeners;

use Illuminate\Events\Dispatcher;
use PKP\config\Config;
use PKP\observers\events\MessageSendingContext;
use PKP\observers\events\MessageSendingSite;

class EnvelopeSenderDefault
{
    /**
     * Maps methods with correspondent events to listen
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            MessageSendingContext::class,
            self::class . '@handleSenderContext'
        );

        $events->listen(
            MessageSendingSite::class,
            self::class . '@handleSenderSite'
        );
    }

    /**
     */
    public function handleSenderContext(MessageSendingContext $event)
    {
        $this->defaultEnvelopeSender($event);
    }

    /**
     */
    public function handleSenderSite(MessageSendingSite $event)
    {
        $this->defaultEnvelopeSender($event);
    }

    /**
     * @param MessageSendingContext|MessageSendingSite $event
     */
    public function defaultEnvelopeSender($event)
    {
        // Force default site-wide envelope sender if set
        $configDefaultEnvelopeSender = Config::getVar('email', 'default_envelope_sender');
        if (Config::getVar('email', 'force_default_envelope_sender') && $configDefaultEnvelopeSender) {
            $event->message->setSender($configDefaultEnvelopeSender);
            return;
        }

        // Don't provide further checks if envelope sender isn't allowed in the config
        if (!Config::getVar('email', 'allow_envelope_sender')) {
            return;
        }

        // Set the sender provided in the context settings
        if (get_class($event) === MessageSendingContext::class && $sender = $event->context->getData('envelopeSender')) {
            $event->message->setSender($sender);
            return;
        }

        // Finally, provide default sender from the config
        if (!$event->message->getSender() && $configDefaultEnvelopeSender) {
            $event->message->setSender($configDefaultEnvelopeSender);
        }
    }
}
