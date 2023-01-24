<?php

/**
 * @file classes/mail/variables/ContextEmailVariable.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextEmailVariable
 * @ingroup mail_variables
 *
 * @brief Represents variables that are associated with a given context (journal or press)
 */

namespace PKP\mail\variables;

use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\core\Dispatcher;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\core\PKPString;
use PKP\mail\Mailable;
use PKP\mail\Mailer;

class ContextEmailVariable extends Variable
{
    public const CONTACT_NAME = 'contactName';
    public const CONTACT_EMAIL = 'contactEmail';
    public const CONTEXT_NAME = 'contextName';
    public const CONTEXT_SIGNATURE = 'contextSignature';
    public const CONTEXT_URL = 'contextUrl';
    public const MAILING_ADDRESS = 'mailingAddress';
    public const PASSWORD_LOST_URL = 'passwordLostUrl';
    public const SUBMISSIONS_URL = 'submissionsUrl';
    public const USER_PROFILE_URL = 'userProfileUrl';

    protected Context $context;
    protected PKPRequest $request;
    protected Dispatcher $dispatcher;

    public function __construct(Context $context, Mailable $mailable)
    {
        parent::__construct($mailable);

        $this->context = $context;
        $application = PKPApplication::get();
        $this->request = $application->getRequest();
        $this->dispatcher = $application->getDispatcher();
    }

    /**
     * @copydoc Variable::descriptions()
     */
    public static function descriptions(): array
    {
        return
        [
            static::CONTEXT_NAME => __('emailTemplate.variable.context.contextName'),
            static::CONTEXT_SIGNATURE => __('emailTemplate.variable.context.contextSignature'),
            static::CONTEXT_URL => __('emailTemplate.variable.context.contextUrl'),
            static::CONTACT_NAME => __('emailTemplate.variable.context.contactName'),
            static::CONTACT_EMAIL => __('emailTemplate.variable.context.contactEmail'),
            static::MAILING_ADDRESS => __('emailTemplate.variable.context.mailingAddress'),
            static::PASSWORD_LOST_URL => __('emailTemplate.variable.context.passwordLostUrl'),
            static::SUBMISSIONS_URL => __('emailTemplate.variable.context.submissionsUrl'),
            static::USER_PROFILE_URL => __('emailTemplate.variable.context.userProfileUrl'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    public function values(string $locale): array
    {
        return
        [
            static::CONTEXT_NAME => $this->context->getLocalizedData('name', $locale),
            static::CONTEXT_URL => $this->getContextUrl(),
            static::CONTACT_NAME => (string) $this->context->getData('contactName'),
            static::CONTACT_EMAIL => (string) $this->context->getData('contactEmail'),
            static::MAILING_ADDRESS => (string) $this->context->getData('mailingAddress'),
            static::PASSWORD_LOST_URL => $this->getPasswordLostUrl(),
            static::SUBMISSIONS_URL => $this->getSubmissionsUrl(),
            static::USER_PROFILE_URL => $this->getUserProfileUrl(),
        ];
    }

    /**
     * Retrieve context; required to generate other email template variables
     */
    public function getContextFromVariable(): Context
    {
        return $this->context;
    }

    protected function getContextUrl(): string
    {
        return $this->dispatcher->url($this->request, PKPApplication::ROUTE_PAGE, $this->context->getData('urlPath'));
    }

    /**
     * Get the CONTEXT_SIGNATURE and render the variable values
     * in the signature
     *
     * @param array $values The values of this email variable
     */
    protected function getContextSignature(array $values): string
    {
        $signature = Mail::compileParams(
            (string) $this->context->getData('emailSignature'), 
            $values
        );
        return $signature
            ? PKPString::stripUnsafeHtml($signature)
            : '';
    }

    /**
     * URL to the lost password page
     */
    protected function getPasswordLostUrl(): string
    {
        return $this->dispatcher->url($this->request, PKPApplication::ROUTE_PAGE, $this->context->getData('urlPath'), 'login', 'lostPassword');
    }

    /**
     * URL to the submissions lists
     */
    protected function getSubmissionsUrl(): string
    {
        return $this->dispatcher->url(
            $this->request,
            PKPApplication::ROUTE_PAGE,
            $this->context->getPath(),
            'submissions',
        );
    }

    /**
     * URL to the user profile page
     */
    protected function getUserProfileUrl(): string
    {
        return $this->dispatcher->url(
            $this->request,
            PKPApplication::ROUTE_PAGE,
            $this->context->getPath(),
            'user',
            'profile'
        );
    }
}
