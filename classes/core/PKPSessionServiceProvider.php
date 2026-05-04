<?php

/**
 * @file classes/core/PKPSessionServiceProvider.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSessionServiceProvider
 *
 * @brief Register session driver, manager and related services
 */

namespace PKP\core;

use APP\core\Application;
use PKP\core\PKPSessionGuard;
use PKP\middleware\PKPStartSession;
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
        $currentWorkingDir = getcwd();

        register_shutdown_function(function () use ($currentWorkingDir) {

            // Restore the current working directory
            // @see https://www.php.net/manual/en/function.register-shutdown-function.php#refsect1-function.register-shutdown-function-notes
            chdir($currentWorkingDir);

            if (PKPSessionGuard::isSessionDisable()) {
                return;
            }

            // need to make sure that all changes to session(via pull/put) are reflected in session storage
            try {
                Application::get()->getRequest()->getSessionGuard()->getSession()->save();
            } catch (\Throwable $e) {
                error_log('Session save failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * @copydoc \Illuminate\Session\SessionServiceProvider::register()
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
