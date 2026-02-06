<?php

/**
 * @file classes/core/AppServiceProvider.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AppServiceProvider
 *
 * @brief Resolves requests for application classes such as the request handler
 *   to support dependency injection
 */

namespace PKP\core;

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\security\RateLimitingService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use PKP\context\Context;
use PKP\editorialTask\EditorialTask;
use PKP\services\PKPFileService;
use PKP\services\PKPSchemaService;
use PKP\services\PKPSiteService;
use PKP\services\PKPStatsContextService;
use PKP\services\PKPStatsGeoService;
use PKP\services\PKPStatsSushiService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services
     *
     * Services registered on the app container here can be automatically
     * injected as dependencies to classes that are instantiated by the
     * app container.
     *
     * @see https://laravel.com/docs/8.x/container#automatic-injection
     * @see https://laravel.com/docs/8.x/providers#the-register-method
     */
    public function register()
    {
        $this->app->singleton('maps', fn ($app) => new MapContainer());

        $this->app->singleton(PKPSchemaService::class, fn ($app) => $app->get('schema'));

        $this->app->singleton(PKPRequest::class, fn ($app) => Application::get()->getRequest());

        $this->app->bind(Context::class, fn ($app) => Application::get()->getRequest()->getContext());

        // File service
        $this->app->singleton('file', fn ($app) => new PKPFileService());

        // Site service
        $this->app->singleton('site', fn ($app) => new PKPSiteService());

        // Schema service
        $this->app->singleton('schema', fn ($app) => new PKPSchemaService());

        // Context statistics service
        $this->app->singleton('contextStats', fn ($app) => new PKPStatsContextService());

        // Geo statistics service
        $this->app->singleton('geoStats', fn ($app) => new PKPStatsGeoService());

        // SUSHI statistics service
        $this->app->singleton('sushiStats', fn ($app) => new PKPStatsSushiService());
    }

    public function boot()
    {
        Relation::enforceMorphMap([
            PKPApplication::ASSOC_TYPE_QUERY => EditorialTask::class,
        ]);

        $this->app->singleton(
            RateLimitingService::class,
            function ($app) {
                $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var \PKP\site\SiteDAO $siteDao */
                return RateLimitingService::getInstance($siteDao->getSite());
            }
        );
    }
}
