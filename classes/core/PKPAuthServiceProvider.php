<?php

/**
 * @file classes/core/PKPAuthServiceProvider.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthServiceProvider
 *
 * @brief Register auth driver, manager and related services
 */

namespace PKP\core;

use PKP\core\PKPAuthManager;
use PKP\core\PKPUserProvider;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

class PKPAuthServiceProvider extends \Illuminate\Auth\AuthServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        Auth::provider(
            PKPUserProvider::AUTH_PROVIDER,
            fn ($app, array $config) => $app->get(PKPUserProvider::class)
        );
    }

    /**
     * @copydoc \Illuminate\Auth\AuthServiceProvider::register()
     */
    public function register()
    {
        parent::register();
        
        $this->app->singleton(AuthFactory::class, fn($app) => $app->get('auth'));

        $this->app->singleton(
            PKPUserProvider::class,
            fn ($app) => new PKPUserProvider(
                $app->get(ConnectionInterface::class),
                new \Illuminate\Hashing\BcryptHasher(),
                'users'
            )
        );

        $this->app->singleton(Guard::class, fn ($app) => $app->get('auth.driver'));

        $this->app->bind(
            \Illuminate\Contracts\Cookie\QueueingFactory::class,
            fn ($app) => $app->get('cookie')
        );
    }

    /**
     * @copydoc \Illuminate\Auth\AuthServiceProvider::registerAuthenticator()
     */
    protected function registerAuthenticator()
    {
        $this->app->singleton('auth', fn ($app) => new PKPAuthManager($app));

        $this->app->singleton('auth.driver', fn ($app) => $app['auth']->guard());
    }
}
