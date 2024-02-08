<?php

namespace PKP\core;

use APP\facades\Repo;
use APP\core\Application;
use InvalidArgumentException;
use PKP\core\PKPSessionGuard;
use PKP\core\PKPUserProvider;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

class PKPAuthManager extends \Illuminate\Auth\AuthManager
{
    /**
     * Create a new Auth manager instance.
     *
     * @param  \Illuminate\Contracts\Container\Container|\Illuminate\Container\Container  $app
     */
    public function __construct($app)
    {
        $this->app = $app;

        $this->userResolver = function ($guard = null) {

            if ($userId = Application::get()->getRequest()->getSession()->get('user_id')) {
                return Repo::user()->get($userId);
            }

            return null;
        };
    }

    /**
     * Create the user provider implementation for the driver.
     *
     * @param  string|null  $provider
     * @return \Illuminate\Contracts\Auth\UserProvider|null
     *
     * @throws \InvalidArgumentException
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
            'database'          => $this->createDatabaseProvider($config),
            'eloquent'          => $this->createEloquentProvider($config),
            'pkp_user_provider' => $this->createPKPUserProvider($config),
            default             => throw new InvalidArgumentException(
                                        "Authentication user provider [{$driver}] is not defined."
                                    ),
        };
    }

    /**
     * Create an instance of the PKPUserProvider.
     *
     * @param  array  $config
     * @return \PKP\core\PKPUserProvider
     */
    public function createPKPUserProvider(array $config = [])
    {
        return app()->get(PKPUserProvider::class);
    }

    /**
     * Create a session based authentication guard.
     *
     * @param  string  $name
     * @param  array  $config
     * 
     * @return \Illuminate\Auth\SessionGuard|\PKP\core\PKPSessionGuard
     */
    public function createSessionDriver($name, $config)
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);

        $guard = new PKPSessionGuard(
            $name,
            $provider,
            $this->app['session.store'],
        );

        // When using the remember me functionality of the authentication services we
        // will need to be set the encryption instance of the guard, which allows
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
