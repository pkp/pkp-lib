<?php

declare(strict_types=1);

/**
 * @file classes/core/RoutingServiceProvider.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RoutingServiceProvider
 * @ingroup core
 *
 * @brief Enables the Laravel router on the application
 */

namespace PKP\core;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class RoutingServiceProvider extends ServiceProvider
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
        $this->app->singleton('router', function ($app) {
            $request = Request::capture();
            $app->instance('Illuminate\Http\Request', $request);

            return new Router(
                $app['events'],
                $app
            );
        });
    }
}
