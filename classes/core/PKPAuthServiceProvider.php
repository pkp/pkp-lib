<?php

namespace PKP\core;

use PKP\core\PKPAuthManager;
use PKP\core\PKPSessionGuard;
use PKP\core\PKPUserProvider;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\SessionGuard;
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
            // return new PKPAuthManager($app);
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
            // return new PKPSessionGuard(
            //     $app->get("config")['auth']["defaults"]["guard"],
            //     $app->get(PKPUserProvider::class),
            //     $app->get("session")->driver(),
            //     $app->get(\Illuminate\Http\Request::class)
            // );
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
            // $guard = $app['auth']->guard();
            // return $guard;
            return $app['auth']->guard();
        });
    }
}
