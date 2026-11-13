<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I13778_RepairRenamedLocaleDefaults.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I13778_RepairRenamedLocaleDefaults
 *
 * @brief Repair the default texts created for the locales renamed by I9707_WeblateUILocales (e.g. fr_FR to fr).
 *
 * Several migrations ran before I9707_WeblateUILocales renamed the locale codes, so they found no translations
 * for these locales: default email templates got the email key as name, were left empty or were not created,
 * and the submission help texts and the checklist introduction were created in another language or as bare keys.
 * Only values that are still in exactly that state are changed, so the migration can run more than once.
 */

namespace PKP\migration\upgrade\v3_5_0;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\db\XMLDAO;
use PKP\facades\Locale;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I13778_RepairRenamedLocaleDefaults extends Migration
{
    protected const HELP_SETTINGS = [
        'beginSubmissionHelp' => 'default.submission.step.beforeYouBegin',
        'contributorsHelp' => 'default.submission.step.contributors',
        'detailsHelp' => 'default.submission.step.details',
        'forTheEditorsHelp' => 'default.submission.step.forTheEditors',
        'reviewHelp' => 'default.submission.step.review',
        'uploadFilesHelp' => 'default.submission.step.uploadFiles',
    ];

    protected const CHECKLIST_DESCRIPTION = 'submission.submit.submissionChecklistDescription';

    public function up(): void
    {
        $installedLocales = json_decode(DB::table('site')->value('installed_locales') ?? '[]', true) ?: [];
        $locales = array_values(array_intersect(I9707_WeblateUILocales::getAffectedLocales(), $installedLocales));
        if (empty($locales)) {
            return;
        }

        $previousMissingKeyHandler = Locale::getMissingKeyHandler();
        Locale::setMissingKeyHandler(fn (string $key): string => '');
        try {
            $this->repairEmailTemplates($locales);
            // The upgrade fell back to the configured default locale: its texts, or the bare keys if it had no translations (e.g. en_US)
            $sourceLocales = array_unique(['en', Locale::getDefaultLocale()]);
            $this->repairContextSettings($locales, $sourceLocales);
        } finally {
            Locale::setMissingKeyHandler($previousMissingKeyHandler);
        }
    }

    /**
     * Reinstall the default email templates that are empty or missing for the given locales,
     * and translate the names that were set to the email key.
     */
    protected function repairEmailTemplates(array $locales): void
    {
        $dao = Repo::emailTemplate()->dao;
        $file = $dao->getMainEmailTemplatesFilename();
        $data = (new XMLDAO())->parseStruct($file, ['email']);

        $nameKeys = [];
        foreach ($data['email'] ?? [] as $entry) {
            $nameKeys[$entry['attributes']['key']] = $entry['attributes']['name'] ?? null;
        }

        $installedKeys = DB::table('email_templates_default_data')->distinct()->pluck('email_key')->all();

        foreach ($locales as $locale) {
            $rows = DB::table('email_templates_default_data')
                ->where('locale', $locale)
                ->get()
                ->keyBy('email_key');

            foreach ($nameKeys as $key => $nameKey) {
                // Templates that are not installed in any locale were removed on purpose
                if (!in_array($key, $installedKeys)) {
                    continue;
                }

                $row = $rows->get($key);
                if (!$row || ((string) $row->subject === '' && (string) $row->body === '')) {
                    $dao->installEmailTemplateLocaleData($file, [$locale], $key);
                    continue;
                }

                if ($row->name === $key && $nameKey && ($name = __($nameKey, [], $locale)) !== '') {
                    DB::table('email_templates_default_data')
                        ->where('email_key', $key)
                        ->where('locale', $locale)
                        ->update(['name' => $name]);
                }
            }
        }
    }

    /**
     * Replace the submission help texts and the checklist introduction that were created in another locale
     * or as bare keys, and not edited since.
     */
    protected function repairContextSettings(array $locales, array $sourceLocales): void
    {
        $contextDao = Application::getContextDAO();
        $settingNames = [...array_keys(self::HELP_SETTINGS), 'submissionChecklist', 'name'];

        $contexts = DB::table($contextDao->tableName)
            ->get([$contextDao->primaryKeyColumn . ' as context_id', 'path', 'primary_locale']);

        foreach ($contexts as $context) {
            $settings = DB::table($contextDao->settingsTableName)
                ->where($contextDao->primaryKeyColumn, $context->context_id)
                ->whereIn('setting_name', $settingNames)
                ->get();

            $contextNames = $settings
                ->where('setting_name', 'name')
                ->filter(fn (object $setting) => (string) $setting->setting_value !== '')
                ->pluck('setting_value', 'locale');

            foreach ($locales as $locale) {
                $otherLocales = array_diff($sourceLocales, [$locale]);

                $params = [
                    'contextName' => $contextNames[$locale] ?? $contextNames[$context->primary_locale] ?? $contextNames->first() ?? '',
                    'submissionGuidelinesUrl' => Application::get()->getDispatcher()->url(
                        Application::get()->getRequest(),
                        Application::ROUTE_PAGE,
                        $context->path,
                        'about',
                        'submissions'
                    ),
                ];

                foreach (self::HELP_SETTINGS as $settingName => $localeKey) {
                    $value = $settings->where('setting_name', $settingName)->firstWhere('locale', $locale)?->setting_value;
                    if ($value === null || !$this->isBrokenDefault($value, $localeKey, $otherLocales)) {
                        continue;
                    }
                    $newValue = __($localeKey, $params, $locale);
                    if ($newValue !== '') {
                        $this->updateSetting($context->context_id, $settingName, $locale, $newValue);
                    }
                }

                $checklist = $settings->where('setting_name', 'submissionChecklist')->firstWhere('locale', $locale)?->setting_value;
                $description = __(self::CHECKLIST_DESCRIPTION, [], $locale);
                if ($checklist === null || $description === '') {
                    continue;
                }
                $brokenDescriptions = [$this->getMissingKeyValue(self::CHECKLIST_DESCRIPTION)];
                foreach ($otherLocales as $otherLocale) {
                    $brokenDescriptions[] = __(self::CHECKLIST_DESCRIPTION, [], $otherLocale);
                }
                foreach ($brokenDescriptions as $brokenDescription) {
                    if ($brokenDescription === '' || $brokenDescription === $description) {
                        continue;
                    }
                    $prefix = '<p>' . $brokenDescription . '</p>';
                    if (str_starts_with($checklist, $prefix)) {
                        $newValue = '<p>' . $description . '</p>' . substr($checklist, strlen($prefix));
                        $this->updateSetting($context->context_id, 'submissionChecklist', $locale, $newValue);
                        break;
                    }
                }
            }
        }
    }

    /**
     * Whether the value is the bare locale key, or the default text of another locale with any parameter values
     */
    protected function isBrokenDefault(string $value, string $localeKey, array $otherLocales): bool
    {
        if ($value === $this->getMissingKeyValue($localeKey)) {
            return true;
        }
        foreach ($otherLocales as $otherLocale) {
            $template = __($localeKey, [], $otherLocale);
            if ($template === '') {
                continue;
            }
            $parts = array_map(fn (string $part) => preg_quote($part, '/'), preg_split('/\{\$\w+\}/', $template));
            if (preg_match('/^' . implode('.*?', $parts) . '$/s', $value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The value Locale::translate() returns for a missing translation when no missing key handler is set
     */
    protected function getMissingKeyValue(string $localeKey): string
    {
        return '##' . htmlentities($localeKey) . '##';
    }

    protected function updateSetting(int $contextId, string $settingName, string $locale, string $value): void
    {
        $contextDao = Application::getContextDAO();
        DB::table($contextDao->settingsTableName)
            ->where($contextDao->primaryKeyColumn, $contextId)
            ->where('setting_name', $settingName)
            ->where('locale', $locale)
            ->update(['setting_value' => $value]);
    }

    /**
     * Reverse the migration
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
