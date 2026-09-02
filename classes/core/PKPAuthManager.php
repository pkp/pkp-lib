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
 *
 * NOTE: this class deliberately does NOT override __construct(). The parent constructor
 * initialises \Illuminate\Auth\AuthManager::$userResolver, and
 * \Illuminate\Auth\AuthServiceProvider::registerAccessGate() builds the authorization Gate as
 * new Gate($app, fn () => call_user_func($app['auth']->userResolver())). An override that only
 * assigns $this->app leaves the resolver null, so every Gate ability check fatals with
 * "call_user_func(): Argument #1 ($callback) must be a valid callback".
 *
 * The inherited resolver delegates to PKPSessionGuard::user() -> PKPUserProvider::retrieveById(),
 * the same single source of truth used by Auth::user() and PKPRequest::getUser(); it must not be
 * replaced with a bespoke lookup (see pkp/pkp-lib#12780).
 */

namespace PKP\core;

use InvalidArgumentException;

class PKPAuthManager extends \Illuminate\Auth\AuthManager
{
    /**
     * @copydoc \Illuminate\Auth\AuthManager::$app
     *
     * @var \Illuminate\Contracts\Foundation\Application|\PKP\core\PKPContainer
     */
    protected $app;

    /**
     * @copydoc \Illuminate\Auth\AuthManager::createUserProvider($provider = null)
     *
     * @param null|mixed $provider
     */
    public function createUserProvider($provider = null)
    {
        if (is_null($config = $this->getProviderConfiguration($provider))) {
            return;
        }

        if (isset($this->customProviderCreators[$driver = ($config['driver'] ?? null)])) {
            return call_user_func(
                $this->customProviderCreators[$driver],
                $this->app,
                $config
            );
        }

        return match ($driver) {
            'database' => $this->createDatabaseProvider($config),
            'eloquent' => $this->createEloquentProvider($config),
            PKPUserProvider::AUTH_PROVIDER => $this->createPKPUserProvider($config),
            default => throw new InvalidArgumentException(
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
