<?php
/**
 * @file classes/decision/steps/Email.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A step in an editorial decision workflow that shows an email composer.
 */

namespace PKP\decision\steps;

use APP\core\Application;
use APP\facades\Repo;
use APP\i18n\AppLocale;
use PKP\components\fileAttachers\BaseAttacher;
use PKP\decision\Step;
use PKP\mail\Mailable;
use PKP\user\User;
use stdClass;

class Email extends Step
{
    /** @var array<BaseAttacher> */
    public array $attachers;
    public bool $canChangeTo = false;
    public bool $canSkip = true;
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
    public function canChangeTo(bool $value): self
    {
        $this->canChangeTo = $value;
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
        $config->canChangeTo = $this->canChangeTo;
        $config->canSkip = $this->canSkip;
        $config->emailTemplates = $this->getEmailTemplates();
        $config->initialTemplateKey = $this->mailable::getEmailTemplateKey();
        $config->toOptions = $this->getToOptions();

        $config->variables = [];
        $config->locales = [];
        $allLocales = AppLocale::getAllLocales();
        foreach ($this->locales as $locale) {
            $config->variables[$locale] = $this->mailable->getData($locale);
            $config->locales[] = [
                'locale' => $locale,
                'name' => $allLocales[$locale],
            ];
        }

        return $config;
    }

    protected function getToOptions(): array
    {
        $toOptions = [];
        foreach ($this->recipients as $user) {
            $toOptions[] = [
                'value' => $user->getId(),
                'label' => $user->getFullName(),
            ];
        }
        return $toOptions;
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
}
