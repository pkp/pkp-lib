<?php

/**
 * @file classes/core/PKPAuthManager.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthManager
 *
 * @brief Register session guard and appropriate user provider to handle authentication
 */

namespace PKP\core;

use APP\facades\Repo;
use APP\core\Application;
use InvalidArgumentException;
use PKP\core\PKPSessionGuard;
use PKP\core\PKPUserProvider;

class PKPAuthManager extends \Illuminate\Auth\AuthManager
{
    /**
     * @copydoc \Illuminate\Auth\AuthManager::$app
     *
     * @var \Illuminate\Contracts\Foundation\Application|\PKP\core\PKPContainer
     */
    protected $app;

    /**
     * @copydoc \Illuminate\Auth\AuthManager::__construct($app)
     */
    public function __construct($app)
    {
        $this->app = $app;

        $this->userResolver = function ($guard = null) {

            if ($userId = Application::get()->getRequest()->getSessionGuard()->getUserId()) {
                return Repo::user()->get($userId);
            }

            return null;
        };
    }

    /**
     * @copydoc \Illuminate\Auth\AuthManager::createUserProvider($provider = null)
     */
    public function createUserProvider($provider = null)
    {
        if (is_null($config = $this->getProviderConfiguration($provider))) {
            return;
        }

        if (isset($this->customProviderCreators[$driver = ($config['driver'] ?? null)])) {
            return call_user_func(
                $this->customProviderCreators[$driver], $this->app, $config
            );
        }

        return match ($driver) {
            'database'                      => $this->createDatabaseProvider($config),
            'eloquent'                      => $this->createEloquentProvider($config),
            PKPUserProvider::AUTH_PROVIDER  => $this->createPKPUserProvider($config),
            default                         => throw new InvalidArgumentException(
                                                "Authentication user provider [{$driver}] is not defined."
                                            ),
        };
    }

    /**
     * Create an instance of the PKPUserProvider.
     */
    public function createPKPUserProvider(array $config = []): PKPUserProvider
    {
        return app()->get(PKPUserProvider::class);
    }

    /**
     * @copydoc \Illuminate\Auth\AuthManager::createSessionDriver($name, $config)
     * 
     * @return \PKP\core\PKPSessionGuard
     */
    public function createSessionDriver($name, $config)
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);

        $guard = new PKPSessionGuard(
            $name,
            $provider,
            $this->app['session.store'],
        );

        // When using the remember me functionality of the authentication services
        // we will need to set the encryption instance of the guard, which allows
        // secure, encrypted cookie values to get generated for those cookies.
        if (method_exists($guard, 'setCookieJar')) {
            $guard->setCookieJar($this->app['cookie']);
        }

        if (method_exists($guard, 'setDispatcher')) {
            $guard->setDispatcher($this->app['events']);
        }

        if (method_exists($guard, 'setRequest')) {
            $guard->setRequest($this->app->refresh('request', $guard, 'setRequest'));
        }

        if (isset($config['remember'])) {
            $guard->setRememberDuration($config['remember']);
        }

        return $guard;
    }
}
