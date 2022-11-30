<?php

/**
 * @file classes/migration/upgrade/v3_4_0/InstallEmailTemplates.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InstallEmailTemplates
 * @brief Install all new email templates for 3.4.
 */

namespace PKP\migration\upgrade\v3_4_0;

use APP\core\Services;
use Exception;
use Illuminate\Support\Facades\DB;
use PKP\db\XMLDAO;
use PKP\facades\Locale;
use PKP\migration\Migration;

abstract class InstallEmailTemplates extends Migration
{
    protected array $emailTemplatesInstalled = [];

    abstract protected function getEmailTemplateKeys(): array;
    abstract protected function getAppVariableNames(): array;

    public function up(): void
    {
        $xmlDao = new XMLDAO();

        $data = $xmlDao->parseStruct('registry/emailTemplates.xml', ['email']);

        if (!isset($data['email'])) {
            throw new Exception('Unable to find <email> entries in registry/emailTemplates.xml.');
        }

        $contextIds = Services::get('context')->getIds();
        $locales = json_decode(
            DB::table('site')->pluck('installed_locales')->first()
        );

        foreach ($data['email'] as $entry) {

            $attrs = $entry['attributes'];

            if (!in_array($attrs['key'], $this->getEmailTemplateKeys())) {
                continue;
            }

            if (DB::table('email_templates_default_data')->where('email_key', $attrs['key'])->exists()) {
                continue;
            }

            $name = $attrs['name'] ?? null;
            $subject = $attrs['subject'] ?? null;
            $body = $attrs['body'] ?? null;

            if (!$name || !$subject || !$body) {
                throw new Exception('Failed to install email template ' . $attrs['key'] . '. Missing name, subject or body attribute.');
            }

            $previous = Locale::getMissingKeyHandler();
            Locale::setMissingKeyHandler(fn (string $key): string => '');

            foreach ($locales as $locale) {
                $translatedName = $name ? __($name, [], $locale) : $attrs['key'];
                $translatedSubject = __($subject, [], $locale);
                $translatedBody = __($body, [], $locale);

                DB::table('email_templates_default_data')->insert([
                    'email_key' => $attrs['key'],
                    'locale' => $locale,
                    'name' => $translatedName,
                    'subject' => $this->renameApplicationVariables($translatedSubject),
                    'body' => $this->renameApplicationVariables($translatedBody),
                ]);
            }

            $this->emailTemplatesInstalled[] = $attrs['key'];

            Locale::setMissingKeyHandler($previous);

            if (isset($attrs['alternateTo'])) {

                $exists = DB::table('email_templates_default_data')
                    ->where('email_key', $attrs['alternateTo'])
                    ->exists();

                if (!$exists) {
                    throw new Exception('Tried to install email template `' . $attrs['key'] . '` as an alternate to `' . $attrs['alternateTo'] . '`, but no default template exists with this key.');
                }

                foreach ($contextIds as $contextId) {
                    DB::table('email_templates')->insert([
                        'email_key' => $attrs['key'],
                        'context_id' => $contextId,
                        'alternate_to' => $attrs['alternateTo'],
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('email_templates_default_data')
            ->whereIn('email_key', $this->emailTemplatesInstalled)
            ->delete();

        DB::table('email_templates')
            ->whereIn('email_key', $this->emailTemplatesInstalled)
            ->delete();
    }

    /**
     * Rename the email template variables that are app-specific
     *
     * @param string $string Email template subject or body to be modified
     */
    protected function renameApplicationVariables(string $string): string
    {
        $map = $this->getAppVariableNames();
        if (empty($map)) {
            return $string;
        }

        $variables = [];
        $replacements = [];
        foreach ($map as $key => $value) {
            $variables[] = '/\{\$' . $key . '\}/';
            $replacements[] = '{$' . $value . '}';
        }

        return preg_replace($variables, $replacements, $string);
    }

}
