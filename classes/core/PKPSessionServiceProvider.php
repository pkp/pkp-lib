<?php

namespace PKP\core;

use PKP\config\Config;
use APP\core\Application;
use PKP\middleware\PKPStartSession;
use Illuminate\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

class PKPSessionServiceProvider extends \Illuminate\Session\SessionServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        register_shutdown_function(function () {

            if (defined('SESSION_DISABLE_INIT')) {
                return;
            }

            // need to make sure that all changes to session(via pull/put) are reflected in session storage
            Application::get()->getRequest()->getSessionGuard()->getSession()->save();
        });
    }

    /**
     * @copydoc \Illuminate\Session\SessionServiceProvider::register()
     */
    public function register()
    {
        $this->registerSessionManager();

        $this->registerSessionDriver();

        $this->registerCookieEncrypter();

        $this->app->singleton(StartSession::class, function ($app) {
            return new PKPStartSession(
                $app->make(SessionManager::class), 
                function () use ($app) {
                    return $app->make(CacheFactory::class);
                }
            );
        });
    }

    /**
     * Register the cookie encrypter if cookie encryption key enable and set in config file
     */
    public function registerCookieEncrypter(): void
    {
        $config = $this->app->get("config")["session"];

        if (empty($config['cookie_encryption_key'])) {
            return;
        }

        $this->app->singleton('encrypter', fn ($app) => new Encrypter($config['cookie_encryption_key']));

        $this->app->singleton(EncrypterContract::class, fn ($app) => $app->get('encrypter'));
    }
}
