<?php
/**
 * @file classes/mailable/Repository.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and edit Mailables.
 */

namespace PKP\mail;

use APP\facades\Repo;
use Illuminate\Support\Facades\Mail;
use PKP\core\PKPString;
use PKP\emailTemplate\EmailTemplate;
use PKP\plugins\HookRegistry;

class Repository
{
    /**
     * @copydoc DAO::getMany()
     */
    public function getMany(int $contextId, ?string $searchPhrase = null): array
    {
        $mailables = [];
        foreach (Mail::getMailables($contextId) as $classname) {
            $mailable = $this->mapMailableProperties($classname);

            if ($searchPhrase && $this->containsSearchPhrase($mailable, $searchPhrase)) {
                $mailables[] = $mailable;
                continue;
            }

            $mailables[] = $mailable;
        }

        return $mailables;
    }

    /**
     * @param array $mailable see result of self::mapMailableProperties()
     * Simple check if mailable's name and description contains a search phrase
     * doesn't look up in associated email templates
     */
    public function containsSearchPhrase(array $mailable, string $searchPhrase): bool
    {
        $searchPhrase = PKPString::strtolower($searchPhrase);

        return str_contains(PKPString::strtolower($mailable['name']), $searchPhrase) ||
            str_contains(PKPString::strtolower($mailable['description']), $searchPhrase);
    }

    /**
     * Retrieve single mailable array mapped according to self::mapMailableProperties
     * Additionally may include custom email templates associated with the mailable
     */
    public function getByMailableClassName(string $className, bool $withCustomTemplateKeys = false, ?int $contextId = null): array
    {
        $mailable = $this->mapMailableProperties($className);
        if (!$withCustomTemplateKeys) {
            return $mailable;
        }

        $collector = Repo::emailTemplate()->getCollector()->filterByMailableClassName($className);
        if ($contextId) {
            $collector = $collector->filterByContext($contextId);
        }
        $templates = Repo::emailTemplate()->getMany($collector);
        foreach ($templates as $template) {
            $mailable['customTemplateKeys'][] = $template->getData('key');
        }

        return $mailable;
    }

    /**
     * Associate mailable with custom templates
     * @param array<EmailTemplate> $customTemplates
     */
    public function edit(array $mailable, array $customTemplates): void
    {
        HookRegistry::call('Mailable::edit', [&$mailable, &$customTemplates]);
        Repo::emailTemplate()->dao->associateWithMailable($mailable['className'], $customTemplates);
    }

    /**
     * @return null|array['property' => 'value'] Mailable properties to return by external calls
     */
    protected function mapMailableProperties(string|Mailable $mailableClassName): ?array
    {
        return [
            'name' => $mailableClassName::getName(),
            'className' => is_string($mailableClassName) ? $mailableClassName : $mailableClassName::class,
            'description' => $mailableClassName::getDescription(),
            'supportsTemplates' => $mailableClassName::getSupportsTemplates(),
            'groupsIds' => $mailableClassName::getGroupIds(),
            'fromRoleIds' => $mailableClassName::getFromRoleIds(),
            'toRoleIds' => $mailableClassName::getToRoleIds(),
            'emailTemplateKey' => $mailableClassName::getEmailTemplateKey(),
        ];
    }
}
