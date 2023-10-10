<?php

/**
 * @file classes/core/AppServiceProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AppServiceProvider
 *
 * @ingroup core
 *
 * @brief Resolves requests for application classes such as the request handler
 *   to support dependency injection
 */

namespace PKP\core;

use APP\core\Application;
use APP\core\Services;
use Illuminate\Support\ServiceProvider;
use PKP\context\Context;
use PKP\services\PKPSchemaService;

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
        $this->app->singleton('maps', function ($app) {
            return new MapContainer();
        });
        $this->app->singleton(PKPSchemaService::class, function ($app) {
            return Services::get('schema');
        });
        $this->app->singleton(PKPRequest::class, function ($app) {
            return Application::get()->getRequest();
        });
        $this->app->bind(Context::class, function ($app) {
            return Application::get()->getRequest()->getContext();
        });
    }
}
