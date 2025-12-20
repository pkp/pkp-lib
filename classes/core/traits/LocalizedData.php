<?php

/**
 * @file classes/core/traits/LocalizedData.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocalizedData
 *
 * @ingroup core_traits
 *
 * @brief A trait for getting localized data from assoc arrays
 *
 */

namespace PKP\core\traits;

use APP\core\Application;
use PKP\facades\Locale;

trait LocalizedData
{
    /**
     * Get a localized value from a multilingual data array
     *
     * @param array $data An assoc array with localized data, where each
     *   key is the localeKey. Example: ['en' => 'Journal', 'de' => 'Zeitschrift']
     */
    protected function getBestLocalizedData(array $data, ?string $preferredLocale = null, ?string &$selectedLocale = null): mixed
    {
        foreach ($this->getLocalePrecedence($preferredLocale) as $locale) {
            if (!empty($data[$locale])) {
                $selectedLocale = $locale;
                return $data[$locale];
            }
        }

        // Fallback: Get the first available piece of data.
        foreach ($data as $locale => $dataValue) {
            if (!empty($dataValue)) {
                $selectedLocale = $locale;
                return $dataValue;
            }
        }

        return null;
    }

    /**
     * Get the locale precedence order for object data in the following order
     *
     * 1. Preferred Locale if provided
     * 2. User's current local
     * 3. Object's default locale if set
     * 4. Context's primary locale if context available
     * 5. Site's primary locale
     */
    public function getLocalePrecedence(?string $preferredLocale = null): array
    {
        $request = Application::get()->getRequest();

        return array_unique(
            array_filter([
                $preferredLocale ?? Locale::getLocale(),
                $this->getDefaultLocale(),
                $request->getContext()?->getPrimaryLocale(),
                $request->getSite()->getPrimaryLocale(),
            ])
        );
    }

    /**
     * Get the default locale
     *
     * Override this method in the object which uses this trait, if the object
     * has a default locale. Most objects don't have a default locale.
     */
    public function getDefaultLocale(): ?string
    {
        return null;
    }
}
