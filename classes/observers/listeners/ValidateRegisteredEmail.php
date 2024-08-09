<?php

/**
 * @file classes/observers/listeners/ValidateRegisteredEmail.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidateRegisteredEmail
 *
 * @ingroup observers_listeners
 *
 * @brief Send an email to a newly registered user asking them to validate their email address
 */

namespace PKP\observers\listeners;

use APP\facades\Repo;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Mail;
use PKP\config\Config;
use PKP\invitation\invitations\registrationAccess\RegistrationAccessInvite;
use PKP\mail\mailables\ValidateEmailContext as ContextMailable;
use PKP\mail\mailables\ValidateEmailSite as SiteMailable;
use PKP\observers\events\UserRegisteredContext;
use PKP\observers\events\UserRegisteredSite;

class ValidateRegisteredEmail
{
    /**
     * Maps methods with correspondent events to listen
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            UserRegisteredContext::class,
            self::class . '@handleContextRegistration'
        );

        $events->listen(
            UserRegisteredSite::class,
            self::class . '@handleSiteRegistration'
        );
    }

    /**
     * @param \PKP\observers\events\UserRegisteredContext
     */
    public function handleContextRegistration(UserRegisteredContext $event): void
    {
        $this->manageEmail($event);
    }

    /**
     */
    public function handleSiteRegistration(UserRegisteredSite $event): void
    {
        $this->manageEmail($event);
    }

    /**
     * Sends mail depending on a source - context or site registration
     *
     * @param UserRegisteredContext|UserRegisteredSite $event
     */
    protected function manageEmail($event): void
    {
        if (!$this->emailValidationRequired()) {
            return;
        }

        $contextId = null;

        // Create and compile email template
        if (get_class($event) === UserRegisteredContext::class) {
            $mailable = new ContextMailable($event->context);
            $mailable->from($event->context->getData('supportEmail'), $event->context->getData('supportName'));

            $contextId = $event->context->getId();
        } else {
            $mailable = new SiteMailable($event->site);
            $mailable->from($event->site->getLocalizedContactEmail(), $event->site->getLocalizedContactName());
        }

        $registrationAccessInvite = new RegistrationAccessInvite();
        $registrationAccessInvite->initialize($event->recipient->getId(), $contextId, null);
        $registrationAccessInvite->invite();
        $registrationAccessInvite->updateMailableWithUrl($mailable);

        $registerTemplate = Repo::emailTemplate()->getByKey($contextId, $mailable::getEmailTemplateKey());

        // Send mail
        $mailable
            ->body($registerTemplate->getLocalizedData('body'))
            ->subject($registerTemplate->getLocalizedData('subject'))
            ->recipients([$event->recipient]);

        Mail::send($mailable);
    }

    protected function emailValidationRequired(): bool
    {
        return (bool) Config::getVar('email', 'require_validation');
    }
}
