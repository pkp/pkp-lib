<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7191_InstallSubmissionHelpDefaults.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7191_InstallSubmissionHelpDefaults
 * @brief Install new localized defaults for submission help text
 */

namespace PKP\migration\upgrade\v3_4_0;

use APP\core\Application;
use Exception;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\facades\Locale;
use stdClass;

abstract class I7191_InstallSubmissionHelpDefaults extends \PKP\migration\Migration
{
    protected string $CONTEXT_TABLE = '';
    protected string $CONTEXT_SETTINGS_TABLE = '';
    protected string $CONTEXT_COLUMN = '';

    public function up(): void
    {
        if (empty($this->CONTEXT_TABLE) || empty($this->CONTEXT_SETTINGS_TABLE) || empty($this->CONTEXT_COLUMN)) {
            throw new Exception('Upgrade could not be completed because required properties for the I7191_InstallSubmissionHelpDefaults migration are undefined.');
        }

        $initialLocale = Locale::getLocale();

        DB::table($this->CONTEXT_TABLE . ' as ct')
            ->leftJoin($this->CONTEXT_SETTINGS_TABLE . ' as cst', function (JoinClause $join) {
                $join->on('ct.' . $this->CONTEXT_COLUMN, '=', 'cst.' . $this->CONTEXT_COLUMN)
                    ->where('cst.setting_name', '=', 'supportedLocales');
            })
            ->get([
                'ct.' . $this->CONTEXT_COLUMN,
                'ct.path',
                'ct.primary_locale',
                'cst.setting_value as supportedLocales'])
            ->each(function (stdClass $row) {
                foreach (json_decode($row->supportedLocales) as $locale) {
                    Locale::setLocale($locale);
                    $localizationParams = $this->getLocalizationParams($row, $locale);
                    DB::table($this->CONTEXT_SETTINGS_TABLE)
                        ->insert(
                            $this->getNewSettings()->map(
                                function ($localeKey, $settingName) use ($row, $locale, $localizationParams) {
                                    return [
                                        $this->CONTEXT_COLUMN => $row->{$this->CONTEXT_COLUMN},
                                        'locale' => $locale,
                                        'setting_name' => $settingName,
                                        'setting_value' => __($localeKey, $localizationParams),
                                    ];
                                }
                            )->toArray()
                        );
                }
            });


        Locale::setLocale($initialLocale);
    }

    public function down(): void
    {
        DB::table($this->CONTEXT_SETTINGS_TABLE)
            ->whereIn('setting_name', $this->getNewSettings()->keys())
            ->delete();
    }

    /**
     * @return Collection [settingName => localeKey]
     */
    protected function getNewSettings(): Collection
    {
        return collect([
            'beginSubmissionHelp' => 'default.submission.step.beforeYouBegin',
            'contributorsHelp' => 'default.submission.step.contributors',
            'detailsHelp' => 'default.submission.step.details',
            'forTheEditorsHelp' => 'default.submission.step.forTheEditors',
            'reviewHelp' => 'default.submission.step.review',
            'uploadFilesHelp' => 'default.submission.step.uploadFiles',
        ]);
    }

    /**
     * Get the params needed to localize some of the default settings
     */
    protected function getLocalizationParams(stdClass $row, string $locale): array
    {
        return [
            'submissionGuidelinesUrl' => Application::get()->getDispatcher()->url(
                Application::get()->getRequest(),
                Application::ROUTE_PAGE,
                $row->path,
                'about',
                'submissions'
            ),
            'contextName' => $this->getContextName($row->{$this->CONTEXT_COLUMN}, $locale, $row->primary_locale),
        ];
    }

    /**
     * Get the name of a context
     *
     * Sorts all names with preferred locale first and primary locale
     * second. Chooses top name after the sort.
     */
    protected function getContextName(int $contextId, string $preferredLocale, string $primaryLocale): string
    {
        $rows = DB::table($this->CONTEXT_SETTINGS_TABLE)
            ->where($this->CONTEXT_COLUMN, $contextId)
            ->where('setting_name', 'name')
            ->whereNotNull('setting_value')
            ->get(['locale', 'setting_value'])
            ->toArray();

        if (empty($rows)) {
            throw new Exception('Upgrade failed because no name was found for context ' . $contextId);
        }

        usort($rows, function ($a, $b) use ($preferredLocale, $primaryLocale) {
            return $a->locale === $preferredLocale
                || ($a->locale === $primaryLocale && $b->locale !== $preferredLocale);
        });

        return $rows[0]->setting_value;
    }
}
