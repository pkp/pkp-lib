<?php

/**
 * @file classes/observers/listeners/ValidateRegisteredEmail.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidateRegisteredEmail
 * @ingroup observers_listeners
 *
 * @brief Send an email to a newly registered user asking them to validate their email address
 */

namespace PKP\observers\listeners;

use APP\facades\Repo;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Mail;
use PKP\config\Config;
use PKP\core\PKPApplication;
use PKP\mail\mailables\ValidateEmailContext as ContextMailable;
use PKP\mail\mailables\ValidateEmailSite as SiteMailable;
use PKP\observers\events\UserRegisteredContext;
use PKP\observers\events\UserRegisteredSite;
use PKP\security\AccessKeyManager;

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

        $accessKeyManager = new AccessKeyManager();
        $accessKey = $accessKeyManager->createKey(
            'RegisterContext',
            $event->recipient->getId(),
            null,
            Config::getVar('email', 'validation_timeout')
        );

        // Create and compile email template
        if (get_class($event) === UserRegisteredContext::class) {
            $mailable = new ContextMailable($event->context);
            $mailable->from($event->context->getData('supportEmail'), $event->context->getData('supportName'));
            $mailable->addData([
                'activateUrl' => PKPApplication::get()->getRequest()->url($event->context->getData('urlPath'), 'user', 'activateUser', [$event->recipient->getUsername(), $accessKey]),
            ]);
            $registerTemplate = Repo::emailTemplate()->getByKey($event->context->getId(), $mailable::getEmailTemplateKey());
        } else {
            $mailable = new SiteMailable($event->site);
            $mailable->from($event->site->getLocalizedContactEmail(), $event->site->getLocalizedContactName());
            $mailable->addData([
                'activateUrl' => PKPApplication::get()->getRequest()->url(null, 'user', 'activateUser', [$event->recipient->getUsername(), $accessKey]),
            ]);
            $registerTemplate = Repo::emailTemplate()->getByKey(PKPApplication::CONTEXT_SITE, $mailable::getEmailTemplateKey());
        }

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
