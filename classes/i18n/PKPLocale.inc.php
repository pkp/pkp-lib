<?php

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/PKPLocale.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPLocale
 * @ingroup i18n
 *
 * @brief Deprecated class, kept only for backwards compatibility with external plugins
 */

namespace PKP\i18n;

use PKP\facades\Locale;

if (!PKP_STRICT_MODE) {

    /**
     * @deprecated The class \PKP\i18n\PKPLocale has been replaced by PKP\facades\Locale
     */
    class PKPLocale
    {
        /**
         * Return the key name of the user's currently selected locale (default
         * is "en_US" for U.S. English).
         *
         * @return string
         * @deprecated 3.4.0.0 The same method is available at \PKP\facades\Locale::getLocale()
         */
        public static function getLocale()
        {
            return Locale::getLocale();
        }

        /**
         * Retrieve the primary locale of the current context.
         *
         * @return string
         * @deprecated 3.4.0.0 The same method is available at \PKP\facades\Locale::getPrimaryLocale(), but before using this method, try to retrieve the locale directly from a nearby context
         */
        public static function getPrimaryLocale()
        {
            return Locale::getPrimaryLocale();
        }

        /**
         * Load a set of locale components. Parameters of mixed length may
         * be supplied, each a LOCALE_COMPONENT_... constant. An optional final
         * parameter may be supplied to specify the locale (e.g. 'en_US').
         * @deprecated 3.4.0.0 All the available locale keys are already loaded
         */
        public static function requireComponents()
        {
        }

        /**
         * Return a list of all available locales.
         *
         * @deprecated 3.4.0.0 Use the \PKP\facades\Locale::getLocales()
         * @return array
         */
        public static function &getAllLocales()
        {
            $locales = array_map(fn (LocaleMetadata $locale) => $locale->getDisplayName(), Locale::getLocales());
            return $locales;
        }
    }

    class_alias('\PKP\i18n\PKPLocale', '\PKPLocale');
}
