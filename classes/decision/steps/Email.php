<?php
/**
 * @file classes/decision/steps/Email.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Email
 *
 * @brief A step in an editorial decision workflow that shows an email composer.
 */

namespace PKP\decision\steps;

use APP\core\Application;
use APP\facades\Repo;
use PKP\components\fileAttachers\BaseAttacher;
use PKP\decision\Step;
use PKP\emailTemplate\EmailTemplate;
use PKP\facades\Locale;
use PKP\mail\Mailable;
use PKP\user\User;
use stdClass;

class Email extends Step
{
    /** @var array<BaseAttacher> */
    public array $attachers;
    public bool $canChangeRecipients = false;
    public bool $canSkip = true;
    public bool $anonymousRecipients = false;
    public array $locales;
    public Mailable $mailable;
    /** @var array<User> */
    public array $recipients;
    public string $type = 'email';

    /**
     * @param array<User> $recipients One or more User objects who are the recipients of this email
     * @param Mailable $mailable The mailable that will be used to send this email
     * @param array<BaseAttacher>
     */
    public function __construct(string $id, string $name, string $description, array $recipients, Mailable $mailable, array $locales, ?array $attachers = [])
    {
        parent::__construct($id, $name, $description);
        $this->attachers = $attachers;
        $this->locales = $locales;
        $this->mailable = $mailable;
        $this->recipients = $recipients;
    }

    /**
     * Can the editor change the recipients of this email
     */
    public function canChangeRecipients(bool $value): self
    {
        $this->canChangeRecipients = $value;
        return $this;
    }

    /**
     * Should the recipient names be shown in the
     * email body and subject when writing the email
     */
    public function anonymizeRecipients(bool $value): self
    {
        $this->anonymousRecipients = $value;

        return $this;
    }

    /**
     * Can the editor skip this email
     */
    public function canSkip(bool $value): self
    {
        $this->canSkip = $value;
        return $this;
    }

    public function getState(): stdClass
    {
        $config = parent::getState();
        $config->attachers = $this->getAttachers();
        $config->canChangeRecipients = $this->canChangeRecipients;
        $config->canSkip = $this->canSkip;
        $config->emailTemplates = $this->getEmailTemplates();
        $config->initialTemplateKey = $this->mailable::getEmailTemplateKey();
        $config->recipientOptions = $this->getRecipientOptions();
        $config->anonymousRecipients = $this->anonymousRecipients;

        $config->variables = [];
        $config->locale = Locale::getLocale();
        $config->locales = [];
        foreach ($this->locales as $locale) {
            $config->variables[$locale] = $this->getVariables($locale);
            $config->locales[] = [
                'locale' => $locale,
                'name' => Locale::getMetadata($locale)->getDisplayName(),
            ];
        }

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
                ->each(fn(EmailTemplate $e) => $emailTemplates->add($e));
        }

        return Repo::emailTemplate()->getSchemaMap()->mapMany($emailTemplates)->toArray();
    }

    protected function getAttachers(): array
    {
        $attachers = [];
        foreach ($this->attachers as $attacher) {
            $attachers[] = $attacher->getState();
        }
        return $attachers;
    }

    /**
     * Format the mailable variables into an array to
     * pass to the Composer component
     */
    protected function getVariables(string $locale): array
    {
        $data = $this->mailable->getData($locale);
        $descriptions = $this->mailable::getDataDescriptions();

        $variables = [];
        foreach ($data as $key => $value) {
            $variables[] = [
                'key' => $key,
                'value' => $value,
                'description' => $descriptions[$key] ?? '',
            ];
        }

        return $variables;
    }
}
