<?php

namespace PKP\core;

use Illuminate\Session\SessionManager;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use PKP\middleware\PKPStartSession;

class PKPSessionServiceProvider extends \Illuminate\Session\SessionServiceProvider
{
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
