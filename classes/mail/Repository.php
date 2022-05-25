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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\core\PKPString;
use PKP\emailTemplate\EmailTemplate;

class Repository
{
    /**
     * Get an array of mailables depending on a context and given search string
     */
    public function getMany(Context $context, ?string $searchPhrase = null): array
    {
        $mailables = [];
        foreach (Mail::getMailables($context) as $classname) {
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
     * Simple check if mailable's name and description contains a search phrase
     * doesn't look up in associated email templates
     * @param array $mailable see result of self::mapMailableProperties()
     */
    protected function containsSearchPhrase(array $mailable, string $searchPhrase): bool
    {
        $searchPhrase = PKPString::strtolower($searchPhrase);

        return str_contains(PKPString::strtolower($mailable['name']), $searchPhrase) ||
            str_contains(PKPString::strtolower($mailable['description']), $searchPhrase);
    }

    /**
     * Get a mailable by its class name
     *
     * @return array A key/value map of the mailable. See self::mapMailableProperties().
     */
    public function getByClass(string $className): array
    {
        return $this->mapMailableProperties($className);

    }

    /**
     * Associate mailable with custom templates
     * @param array<EmailTemplate> $customTemplates
     */
    public function edit(string $className, array $customTemplates): void
    {
        // Remove already assigned first
        DB::table('mailable_templates')->where('mailable', $className)->delete();

        // TODO remove suuport Don't allow an email template assignment to multiple mailables, replace previous assignment with the current
        foreach ($customTemplates as $emailTemplate){
            DB::table('mailable_templates')
                ->updateOrInsert(['email_id' => $emailTemplate->getId()], ['mailable' => $className]);
        }
    }

    /**
     * Get the properties of a mailable in a key/value array
     */
    protected function mapMailableProperties(string $mailableClassName): ?array
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
