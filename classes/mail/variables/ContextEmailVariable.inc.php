<?php

/**
 * @file classes/mail/variables/ContextEmailVariable.inc.php
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

use PKP\context\Context;
use PKP\core\Dispatcher;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\core\PKPString;

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

    protected Context $context;

    protected PKPRequest $request;

    protected Dispatcher $dispatcher;

    public function __construct(Context $context)
    {
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
            self::CONTACT_NAME => __('emailTemplate.variable.context.contactName'),
            self::CONTACT_EMAIL => __('emailTemplate.variable.context.contactEmail'),
            self::MAILING_ADDRESS => __('emailTemplate.variable.context.mailingAddress'),
            self::PASSWORD_LOST_URL => __('emailTemplate.variable.context.passwordLostUrl'),
            self::SUBMISSIONS_URL => __('emailTemplate.variable.context.passwordLostUrl'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    public function values(string $locale): array
    {
        return
        [
            self::CONTACT_NAME => (string) $this->context->getData('contactName'),
            self::CONTACT_EMAIL => (string) $this->context->getData('contactEmail'),
            self::MAILING_ADDRESS => (string) $this->context->getData('mailingAddress'),
            self::PASSWORD_LOST_URL => $this->getPasswordLostUrl(),
            self::SUBMISSIONS_URL => $this->getSubmissionsUrl(),
        ];
    }

    protected function getContextUrl(): string
    {
        return $this->dispatcher->url($this->request, PKPApplication::ROUTE_PAGE, $this->context->getData('urlPath'));
    }

    protected function getContextSignature() : string
    {
        $signature = $this->context->getData('emailSignature');
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
        $request = PKPApplication::get()->getRequest();
        return $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'submissions',
        );
    }
}
