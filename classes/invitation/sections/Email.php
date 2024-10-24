<?php
/**
 * @file classes/invitation/sections/Email.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Email
 *
 * @brief A section in an invitation workflow that shows an email composer.
 */

namespace PKP\invitation\sections;

use APP\core\Application;
use APP\facades\Repo;
use Exception;
use PKP\emailTemplate\EmailTemplate;
use PKP\facades\Locale;
use PKP\mail\Mailable;
use PKP\user\User;
use stdClass;

class Email extends Section
{
    public bool $anonymousRecipients = false;
    public array $locales;
    public Mailable $mailable;
    public array $recipients;
    public string $type = 'email';

    /**
     * @param array<User> $recipients One or more User objects who are the recipients of this email
     * @param Mailable $mailable The mailable that will be used to send this email
     *
     * @throws Exception
     */
    public function __construct(string $id, string $name, string $description, array $recipients, Mailable $mailable, array $locales)
    {
        parent::__construct($id, $name, $description);
        $this->locales = $locales;
        $this->mailable = $mailable;
        $this->recipients = $recipients;
    }

    public function getState(): stdClass
    {
        $config = parent::getState();
        $config->canChangeRecipients = false;
        $config->canSkip = false;
        $config->emailTemplates = $this->getEmailTemplates();
        $config->initialTemplateKey = $this->mailable::getEmailTemplateKey();
        $config->recipientOptions = $this->getRecipientOptions();
        $config->anonymousRecipients = $this->anonymousRecipients;
        $config->variables = [];
        $config->locale = Locale::getLocale();
        $config->locales = [];
        return $config;
    }

    protected function getRecipientOptions(): array
    {
        $recipientOptions = [];
        foreach ($this->recipients as $user) {
            $names = [];
            foreach ($this->locales as $locale) {
                $names[$locale] = $user->getFullName(true, false, $locale);
            }
            $recipientOptions[] = [
                'value' => $user->getId(),
                'label' => $names,
            ];
        }
        return $recipientOptions;
    }

    protected function getEmailTemplates(): array
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $emailTemplates = collect();
        if ($this->mailable::getEmailTemplateKey()) {
            $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), $this->mailable::getEmailTemplateKey());
            if ($emailTemplate) {
                $emailTemplates->add($emailTemplate);
            }
            Repo::emailTemplate()
                ->getCollector($context->getId())
                ->alternateTo([$this->mailable::getEmailTemplateKey()])
                ->getMany()
                ->each(fn (EmailTemplate $e) => $emailTemplates->add($e));
        }

        return Repo::emailTemplate()->getSchemaMap()->mapMany($emailTemplates)->toArray();
    }
}
