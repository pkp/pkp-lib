<?php

/**
 * @file classes/author/creditRole/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to manage actions related to credit roles
 */

namespace PKP\author\creditRole;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use PKP\author\creditRole\CreditRoleDegree;
use APP\core\Application;
use PKP\core\Core;
use PKP\facades\Locale;

class Repository {
    /** @var string Max lifetime for the cache */
    protected const MAX_CACHE_LIFETIME = '1 year';
    protected array $localeMapping = [
        'cs' => 'cz',
        'da' => 'dk',
        'zh_Hans' => 'cn',
        'el' => 'gr',
        'nb_NO' => 'no_bk',
        'nn' => 'no_nn',
        'zh_Hant' => 'tc',
    ];
    // One of the credit role file locales
    protected string $defaultLocale = 'en';
    protected array $creditRoleTerms = [];

    /**
     * Get credit role terms and degrees of contribution
     */
    public function getTerms(?string $locale = null): array
    {
        $locale ??= Locale::getLocale();
        $key = __METHOD__ . static::MAX_CACHE_LIFETIME;

        if (!$this->creditRoleTerms) {
            $this->creditRoleTerms = Cache::get($key) ?? [];
        }

        if (!array_key_exists($locale, $this->creditRoleTerms)) {
            $this->getLocalizedTerms($locale, $key);
        }

        return $this->creditRoleTerms[$locale] ?? $this->creditRoleTerms[$this->defaultLocale] ?? [];
    }

    /**
     * Type of roles in an associative array URI => Term
     * Degrees in an associative array db-value => translation
     */
    protected function getLocalizedTerms(string $locale, string $key): void
    {
        $expiration = \DateInterval::createFromDateString(static::MAX_CACHE_LIFETIME);

        $setRoles = fn (array $roles, string $localeKey): array => [
            'roles' => $roles,
            'degrees' => [
                CreditRoleDegree::NULL->getLabel() => '',
                CreditRoleDegree::LEAD->getLabel() => __('submission.submit.creditRoles.degrees.lead', [], $localeKey),
                CreditRoleDegree::EQUAL->getLabel() => __('submission.submit.creditRoles.degrees.equal', [], $localeKey),
                CreditRoleDegree::SUPPORTING->getLabel() => __('submission.submit.creditRoles.degrees.supporting', [], $localeKey),
            ],
        ];
        $getJson = fn (string $file): array => is_array($json = json_decode(file_get_contents($file) ?: "", true)) ? $json : [];
        $getRoles = fn (array $json): array => Arr::map($json['translations'] ?? [], fn (array $items) => $items['name']);

        // Init with ui locales and default locale
        if (!$this->creditRoleTerms) {
            $this->creditRoleTerms = Cache::remember($key, $expiration, fn () =>
                collect(Application::get()->getRequest()->getContext()->getSupportedLocales())
                    ->push($this->defaultLocale)
                    ->unique()
                    ->mapWithKeys(fn (string $l): array => [$l => ($filename = $this->getFileName($l)) ? $setRoles($getRoles($getJson($filename)), $l) : null])
                    ->toArray()
            );
        }

        // Add new locale if not in array
        if (!array_key_exists($locale, $this->creditRoleTerms)) {
            $filename = $this->getFileName($locale);
            $this->creditRoleTerms[$locale] = $filename ? $setRoles($getRoles($getJson($filename)), $locale) : null;
            Cache::put($key, $this->creditRoleTerms, $expiration);
        }
    }

    /**
     * Get file name of the localized terms
     */
    protected function getFileName(string $locale): ?string
    {
        static $filenames;
        $prefix = Core::getBaseDir() . '/' . PKP_LIB_PATH . "/lib/creditRoles/translations/";
        $suffix = '.json';
        $filenames ??= collect(glob("{$prefix}*{$suffix}"));
        // Determine the language we're looking for from $locale
        $language = \Locale::getPrimaryLanguage($locale);
        // Get a list of available options from the filesystem and return file name
        return $filenames
            ->first(fn (string $filename) =>
                // 1. Look for an exact match and return it.
                ($filename === "{$prefix}{$locale}{$suffix}") ||
                // 2. Look in the preference list for a preferred fallback.
                (isset($this->localeMapping[$locale]) && $filename === "{$prefix}{$this->localeMapping[$locale]}{$suffix}") ||
                // 3. Find the first match by language.
                (strpos($filename, "{$prefix}{$language}{$suffix}") === 0 || strpos($filename, "{$prefix}{$language}_") === 0)
            );
    }
}
