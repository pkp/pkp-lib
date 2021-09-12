<?php
declare(strict_types = 1);

/**
 * @defgroup i18n I18N
 * Implements localization concerns such as locale files, time zones, and country lists.
 */

/**
 * @file classes/i18n/LocaleServiceProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LocaleServiceProvider
 * @ingroup i18n
 *
 * @brief Service provider for the i18n features
 */

namespace PKP\i18n;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\ServiceProvider;
use PKP\i18n\interfaces\LocaleInterface;

class LocaleServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(LocaleInterface::class, function () {
            return $this->app->make(Locale::class);
        });
        // Replaces the default Laravel translator
        $this->app->alias(LocaleInterface::class, 'translator');
        $this->app->alias(LocaleInterface::class, Translator::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [LocaleInterface::class, Translator::class, 'translator'];
    }
}
