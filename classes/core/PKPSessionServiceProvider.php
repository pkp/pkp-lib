<?php

namespace PKP\core;

use APP\core\Application;
use PKP\middleware\PKPStartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

class PKPSessionServiceProvider extends \Illuminate\Session\SessionServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     */
    public function boot()
    {
        register_shutdown_function(function () {
            if (Application::get()->isUnderMaintenance()) {
                return;
            }

            // need to make sure that all changes to session(via pull/put) are reflected in session storage
            Application::get()->getRequest()->getSessionGuard()->getSession()->save();
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerSessionManager();

        $this->registerSessionDriver();

        $this->app->singleton(StartSession::class, function ($app) {
            return new PKPStartSession(
                $app->make(SessionManager::class), 
                function () use ($app) {
                    return $app->make(CacheFactory::class);
                }
            );
        });
    }
}
