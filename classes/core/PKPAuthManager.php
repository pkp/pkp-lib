<?php

namespace PKP\core;

use APP\facades\Repo;
use APP\core\Application;
use InvalidArgumentException;
use PKP\core\PKPUserProvider;

class PKPAuthManager extends \Illuminate\Auth\AuthManager
{
    /**
     * Create a new Auth manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;

        $this->userResolver = function ($guard = null) {
            $session = session();

            if ($session && ($userId = $session->get('user_id'))) {
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
            'database' => $this->createDatabaseProvider($config),
            'eloquent' => $this->createEloquentProvider($config),
            'pkp_user_provider' => $this->createPKPUserProvider($config),
            default => throw new InvalidArgumentException(
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
        return new PKPUserProvider();
    }
}
