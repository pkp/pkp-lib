<?php

namespace PKP\core;

use PKP\core\PKPAuthManager;
use PKP\core\PKPUserProvider;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

class PKPAuthServiceProvider extends \Illuminate\Auth\AuthServiceProvider
{
    public function boot()
    {
        Auth::provider(
            'pkp_user_provider',
            fn ($app, array $config) => $app->get(PKPUserProvider::class)
        );
    }

    /**
     * Register the service provider.
     *
     * @return void
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
     * Register the authenticator services.
     *
     * @return void
     */
    protected function registerAuthenticator()
    {
        $this->app->singleton('auth', fn ($app) => new PKPAuthManager($app));

        $this->app->singleton('auth.driver', fn ($app) => $app['auth']->guard());
    }
}
