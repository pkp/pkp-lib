<?php

namespace PKP\core;

use PKP\core\PKPAuthManager;
use PKP\core\PKPSessionGuard;
use PKP\core\PKPUserProvider;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

class PKPAuthServiceProvider extends \Illuminate\Auth\AuthServiceProvider
{
    public function boot()
    {
        Auth::provider('pkp_user_provider', function ($app, array $config) {
            return new PKPUserProvider();
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
            return new PKPAuthManager($app);
        });

        $this->app->singleton(Guard::class, function ($app) {
            return new PKPSessionGuard(
                app()->get("config")['auth']["defaults"]["guard"],
                new PKPUserProvider(),
                app()->get("session")->driver(),
                app(\Illuminate\Http\Request::class)
            );
        });
    }
}
