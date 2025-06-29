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
        'cz' => ['cs'],
        'fr' => ['fr', 'fr_CA'],
        'cn' => ['zh_Hans'],
        'gr' => ['el'],
        'no_bk' => ['nb_NO'],
        'no_nn' => ['nn'],
        'tc' => ['zh_Hant'],
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
            $this->creditRoleTerms = Cache::get($key)[0] ?? [];
        }

        if (!isset($this->creditRoleTerms[$locale])) {
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
        $creditRoleLocales = Cache::get($key)[1] ?? [];
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

        if (!$this->creditRoleTerms || !$creditRoleLocales) {
            [$creditRoleTerms, $creditRoleLocales] = Cache::remember($key, $expiration, function () use ($setRoles, $getJson, $getRoles): array {
                $folder = Core::getBaseDir() . '/' . PKP_LIB_PATH . "/lib/creditRoles/translations";
                $uiLocales = array_unique(array_merge([$this->defaultLocale], Application::get()->getRequest()->getContext()->getSupportedLocales()));
                $creditRoleLocales = [];
                $creditRoleTerms = [];
                foreach (new \DirectoryIterator($folder) as $cursor) {
                    if ($cursor->isDot()) continue;
                    $file = "$folder/" . $cursor->getBasename();
                    $json = $getJson($file);
                    $jsonLocaleKey = $json['metadata']['languageCode'] ?? '';
                    $jsonLocaleKeysMapped = $this->localeMapping[$jsonLocaleKey] ?? [$jsonLocaleKey];
                    $localeKeys = array_intersect($uiLocales, $jsonLocaleKeysMapped);
                    if ($localeKeys) {
                        $roles = $getRoles($json);
                        if (!$roles) continue;
                        foreach ($localeKeys as $localeKey) {
                            $creditRoleTerms[$localeKey] = $setRoles($roles, $localeKey);
                        }
                    }
                    $creditRoleLocales = $creditRoleLocales + Arr::mapWithKeys($jsonLocaleKeysMapped, fn (string $l): array => [$l => $file]);
                }
                return [$creditRoleTerms, $creditRoleLocales];
            });
            $this->creditRoleTerms = $creditRoleTerms;
        }

        if (!isset($this->creditRoleTerms[$locale]) && isset($creditRoleLocales[$locale])) {
            if ($roles = $getRoles($getJson($creditRoleLocales[$locale]))) {
                $this->creditRoleTerms[$locale] = $setRoles($roles, $locale);
                Cache::put($key, [$this->creditRoleTerms, $creditRoleLocales], $expiration);
            }
        }
    }
}
