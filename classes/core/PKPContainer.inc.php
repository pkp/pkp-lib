<?php

/**
 * @file classes/core/PKPContainer.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContainer
 * @ingroup core
 *
 * @brief Bootstraps Laravel services, application-level parts and creates bindings
 */

namespace PKP\core;

use APP\core\AppServiceProvider;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel as KernelContract;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Console\Kernel;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Support\Facades\Facade;

use PKP\config\Config;

use Throwable;

class PKPContainer extends Container
{
    /**
     * @var string
     * @brief the base path of the application, needed for base_path helper
     */
    protected $basePath;

    /**
     * @brief Create own container instance, initialize bindings
     */
    public function __construct()
    {
        $this->basePath = BASE_SYS_DIR;
        $this->registerBaseBindings();
        $this->registerCoreContainerAliases();
    }

    /**
     * @brief Bind the current container and set it globally
     * let helpers, facades and services know to which container refer to
     */
    protected function registerBaseBindings()
    {
        static::setInstance($this);
        $this->instance('app', $this);
        $this->instance('path', $this->basePath);
        $this->instance(Container::class, $this);
        $this->instance(ContainerContract::class, $this);
        $this->singleton(ExceptionHandler::class, function () {
            return new class() implements ExceptionHandler {
                public function shouldReport(Throwable $e)
                {
                    return true;
                }

                public function report(Throwable $e)
                {
                    error_log((string) $e);
                }

                public function render($request, Throwable $e)
                {
                    return null;
                }

                public function renderForConsole($output, Throwable $e)
                {
                    echo (string) $e;
                }
            };
        });
        $this->singleton(
            KernelContract::class,
            Kernel::class
        );

        $this->singleton(
            'queue.failer',
            function ($app) {
                return new DatabaseFailedJobProvider(
                    $app['db'],
                    config('queue.failed.database'),
                    config('queue.failed.table')
                );
            }
        );

        Facade::setFacadeApplication($this);
    }

    /**
     * @brief Register used service providers within the container
     */
    public function registerConfiguredProviders()
    {
        // Load main settings, this should be done before registering services, e.g., it's used by Database Service
        $this->loadConfiguration();

        $this->register(new PKPEventServiceProvider($this));
        $this->register(new \Illuminate\Database\DatabaseServiceProvider($this));
        $this->register(new \Illuminate\Bus\BusServiceProvider($this));
        $this->register(new \Illuminate\Queue\QueueServiceProvider($this));
        $this->register(new PKPQueueProvider());
        $this->register(new AppServiceProvider($this));
    }

    /**
     * @param \Illuminate\Support\ServiceProvider $provider
     * @brief Simplified service registration
     */
    public function register($provider)
    {
        $provider->register();
        if (method_exists($provider, 'boot')) {
            $provider->boot();
        }
    }

    /**
     * @brief Bind aliases with contracts
     */
    public function registerCoreContainerAliases()
    {
        foreach ([
            'app' => [self::class, \Illuminate\Contracts\Container\Container::class, \Psr\Container\ContainerInterface::class],
            'config' => [\Illuminate\Config\Repository::class, \Illuminate\Contracts\Config\Repository::class],
            'db' => [\Illuminate\Database\DatabaseManager::class, \Illuminate\Database\ConnectionResolverInterface::class],
            'db.connection' => [\Illuminate\Database\Connection::class, \Illuminate\Database\ConnectionInterface::class],
            'maps' => [MapContainer::class, MapContainer::class],
            'events' => [\Illuminate\Events\Dispatcher::class, \Illuminate\Contracts\Events\Dispatcher::class],
            'queue' => [\Illuminate\Queue\QueueManager::class, \Illuminate\Contracts\Queue\Factory::class, \Illuminate\Contracts\Queue\Monitor::class],
            'queue.connection' => [\Illuminate\Contracts\Queue\Queue::class],
            'queue.failer' => [\Illuminate\Queue\Failed\FailedJobProviderInterface::class],
        ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    /**
     * @brief Bind and load container configurations
     * usage from Facade, see Illuminate\Support\Facades\Config
     */
    protected function loadConfiguration()
    {
        $items = [];

        // Database connection
        $driver = 'mysql';

        if (substr(strtolower(Config::getVar('database', 'driver')), 0, 8) === 'postgres') {
            $driver = 'pgsql';
        }

        $items['database']['default'] = $driver;
        $items['database']['connections'][$driver] = [
            'driver' => $driver,
            'host' => Config::getVar('database', 'host'),
            'database' => Config::getVar('database', 'name'),
            'username' => Config::getVar('database', 'username'),
            'port' => Config::getVar('database', 'port'),
            'unix_socket' => Config::getVar('database', 'unix_socket'),
            'password' => Config::getVar('database', 'password'),
            'charset' => Config::getVar('i18n', 'connection_charset', 'utf8'),
            'collation' => Config::getVar('database', 'collation', 'utf8_general_ci'),
        ];

        // Queue connection
        $items['queue']['default'] = 'database';
        $items['queue']['connections']['sync']['driver'] = 'sync';
        $items['queue']['connections']['database'] = [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => true,
        ];
        $items['queue']['failed'] = [
            'driver' => 'database',
            'database' => $driver,
            'table' => 'failed_jobs',
        ];

        $this->instance('config', new Repository($items)); // create instance and bind to use globally
    }

    /**
     * @param string $path appended to the base path
     * @brief see Illuminate\Foundation\Application::basePath
     */
    public function basePath($path = '')
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * @param string $path appended to the path
     * @brief alias of basePath(), Laravel app path differs from installation path
     */
    public function path($path = '')
    {
        return $this->basePath($path);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPContainer', '\PKPContainer');
}
