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
        Auth::provider('pkp_user_provider', function ($app, array $config) {
            return $app->get(PKPUserProvider::class);
        });

        // app()->get('auth.driver')->setRememberDuration(
        //     app()->get('config')['session']['lifetime'] * 2
        // );
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        parent::register();
        
        $this->app->singleton(AuthFactory::class, function($app) {
            return $app->get('auth');
        });

        $this->app->singleton(PKPUserProvider::class, function ($app) {
            return new PKPUserProvider(
                $app->get(ConnectionInterface::class),
                new \Illuminate\Hashing\BcryptHasher(),
                'users'
            );
        });

        $this->app->singleton(Guard::class, function ($app) {
            return $app->get('auth.driver');
        });

        $this->app->bind(\Illuminate\Contracts\Cookie\QueueingFactory::class, function ($app) {
            return $app->get('cookie');
        });
    }

    /**
     * Register the authenticator services.
     *
     * @return void
     */
    protected function registerAuthenticator()
    {
        $this->app->singleton('auth', fn ($app) => new PKPAuthManager($app));

        $this->app->singleton('auth.driver', function ($app) {
            return $app['auth']->guard();
        });
    }
}
