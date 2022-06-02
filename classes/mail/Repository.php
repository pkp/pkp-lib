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

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\context\ContextDAO;
use PKP\core\PKPString;
use PKP\emailTemplate\EmailTemplate;
use Exception;

class Repository
{
    /**
     * Get an array of mailables depending on a context and given search string
     */
    public function getMany(Context $context, ?string $searchPhrase = null): array
    {
        $mailables = [];
        foreach (Mail::getMailables($context) as $classname) {
            if (!$searchPhrase || $this->containsSearchPhrase($classname, $searchPhrase)) {
                $mailables[] = $classname;
            }
        }

        return $mailables;
    }

    /**
     * Simple check if mailable's name and description contains a search phrase
     * doesn't look up in associated email templates
     * @param string $className the fully qualified class name of the Mailable
     */
    protected function containsSearchPhrase(string $className, string $searchPhrase): bool
    {
        $searchPhrase = PKPString::strtolower($searchPhrase);

        /** @var Mailable $className */
        return str_contains(PKPString::strtolower($className::getName()), $searchPhrase) ||
            str_contains(PKPString::strtolower($className::getDescription()), $searchPhrase);
    }

    /**
     * Get a mailable by its id
     *
     * @return ?string fully qualified class name.
     */
    public function get(string $id, Context $context): ?string
    {
        $mailables = $this->getMany($context);
        foreach ($mailables as $mailable) {
            if ($mailable::getId() === $id) {
                return $mailable;
            }
        }
        return null;
    }

    /**
     * Associate mailable with custom templates
     * @param array<EmailTemplate> $customTemplates
     * @throws Exception
     */
    public function assignTemplates(string $id, array $customTemplates): void
    {
        // Remove already assigned first
        DB::table('mailable_templates')->where('mailable_id', $id)->delete();

        // Allow one-to-many relationship between templates and mailables
        $checkedIds = [];
        $contextDao = Application::getContextDAO(); /** @var ContextDAO $contextDao */
        foreach ($customTemplates as $emailTemplate) {

            // Data integrity check whether Mailable exists and is in the same context with the template
            $contextId = $emailTemplate->getData('contextId');
            $mailableExists = in_array($contextId, $checkedIds);
            if (!$mailableExists) {
                $context = $contextDao->getById($contextId);
                $mailableExists = (bool) $this->get($id, $context);
            }

            if ($mailableExists) {
                DB::table('mailable_templates')
                    ->insert(['email_id' => $emailTemplate->getId(), 'mailable_id' => $id]);
            } else {
                throw new Exception('Tried to insert unexisting Mailable ' . $id);
            }
        }
    }
}
