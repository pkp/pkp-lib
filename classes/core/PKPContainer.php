<?php

/**
 * @file classes/core/PKPContainer.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContainer
 *
 * @ingroup core
 *
 * @brief Bootstraps Laravel services, application-level parts and creates bindings
 */

namespace PKP\core;

use APP\core\Application;
use APP\core\AppServiceProvider;
use Exception;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel as KernelContract;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Events\EventServiceProvider as LaravelEventServiceProvider;
use Illuminate\Foundation\Console\Kernel;
use Illuminate\Http\Response;
use Illuminate\Log\LogServiceProvider;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use PKP\core\PKPAppKey;
use PKP\core\ConsoleCommandServiceProvider;
use PKP\core\ScheduleServiceProvider;
use PKP\config\Config;
use PKP\i18n\LocaleServiceProvider;
use PKP\proxy\ProxyParser;
use Throwable;

class PKPContainer extends Container
{
    /**
     * The base path of the application, needed for base_path helper
     */
    protected string $basePath;

    /**
     * Create own container instance, initialize bindings
     */
    public function __construct()
    {
        $this->basePath = BASE_SYS_DIR;
        $this->settingProxyForStreamContext();
        $this->registerBaseBindings();
        $this->registerCoreContainerAliases();
    }

    /**
     * Get the proper database driver
     */
    public static function getDatabaseDriverName(?string $driver = null): string
    {
        $driver ??= Config::getVar('database', 'driver');

        if (substr(strtolower($driver), 0, 8) === 'postgres') {
            return 'pgsql';
        }

        return match ($driver) {
            'mysql', 'mysqli' => 'mysql',
            'mariadb' => 'mariadb'
        };
    }

    /**
     * Bind the current container and set it globally
     * let helpers, facades and services know to which container refer to
     */
    protected function registerBaseBindings(): void
    {
        static::setInstance($this);
        $this->instance('app', $this);
        $this->instance(Container::class, $this);
        $this->instance('path', $this->basePath);
        $this->singleton(ExceptionHandler::class, function () {
            return new class () implements ExceptionHandler {
                public function shouldReport(Throwable $exception)
                {
                    return true;
                }

                public function report(Throwable $exception)
                {
                    error_log($exception->__toString());
                }

                public function render($request, Throwable $exception)
                {
                    $pkpRouter = Application::get()->getRequest()->getRouter();

                    if($pkpRouter instanceof APIRouter && app('router')->getRoutes()->count()) {
                        return response()->json(
                            [
                                'error' => $exception->getMessage()
                            ],
                            in_array($exception->getCode(), array_keys(Response::$statusTexts))
                                ? $exception->getCode()
                                : Response::HTTP_INTERNAL_SERVER_ERROR
                        )->send();
                    }

                    return null;
                }

                public function renderForConsole($output, Throwable $exception)
                {
                    echo (string) $exception;
                }
            };
        });

        $this->singleton(
            KernelContract::class,
            Kernel::class
        );

        $this->singleton('pkpJobQueue', fn ($app) => new PKPQueueProvider($app));

        $this->singleton(
            'queue.failer',
            fn ($app) => new DatabaseFailedJobProvider(
                $app['db'],
                config('queue.failed.database'),
                config('queue.failed.table')
            )
        );

        $this->app->singleton('request', fn ($app) => \Illuminate\Http\Request::createFromGlobals());

        $this->app->singleton(\Illuminate\Http\Request::class, fn ($app) => $app->get('request'));

        $this->app->singleton(
            'response',
            fn ($app) => new \Illuminate\Http\Response(headers: $app->get('request')->headers->all())
        );

        $this->app->singleton(\Illuminate\Http\Response::class, fn ($app) => $app->get('response'));

        Facade::setFacadeApplication($this);
    }

    /**
     * Register used service providers within the container
     */
    public function registerConfiguredProviders(): void
    {
        // Load main settings, this should be done before registering services, e.g., it's used by Database Service
        $this->loadConfiguration();

        $this->register(new AppServiceProvider($this));
        $this->register(new \PKP\core\PKPEncryptionServiceProvider($this));
        $this->register(new \PKP\core\PKPAuthServiceProvider($this));
        $this->register(new \Illuminate\Cookie\CookieServiceProvider($this));
        $this->register(new \PKP\core\PKPSessionServiceProvider($this));
        $this->register(new \Illuminate\Pipeline\PipelineServiceProvider($this));
        $this->register(new \Illuminate\Cache\CacheServiceProvider($this));
        $this->register(new \Illuminate\Filesystem\FilesystemServiceProvider($this));
        $this->register(new \ElcoBvg\Opcache\ServiceProvider($this));
        $this->register(new LaravelEventServiceProvider($this));
        $this->register(new EventServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new \Illuminate\Database\DatabaseServiceProvider($this));
        $this->register(new \Illuminate\Bus\BusServiceProvider($this));
        $this->register(new PKPQueueProvider($this));
        $this->register(new MailServiceProvider($this));
        $this->register(new LocaleServiceProvider($this));
        $this->register(new PKPRoutingProvider($this));
        $this->register(new InvitationServiceProvider($this));
        $this->register(new ScheduleServiceProvider($this));
        $this->register(new ConsoleCommandServiceProvider($this));
    }

    /**
     * Simplified service registration
     */
    public function register(\Illuminate\Support\ServiceProvider $provider): void
    {
        $provider->register();

        $provider->callBootingCallbacks();

        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }

        // If there are bindings / singletons set as properties on the provider we
        // will spin through them and register them with the application, which
        // serves as a convenience layer while registering a lot of bindings.
        if (property_exists($provider, 'bindings')) {
            /** @disregard P1014 PHP Intelephense error suppression */
            foreach ($provider->bindings as $key => $value) {
                $this->bind($key, $value);
            }
        }

        if (property_exists($provider, 'singletons')) {
            /** @disregard P1014 PHP Intelephense error suppression */
            foreach ($provider->singletons as $key => $value) {
                $key = is_int($key) ? $value : $key;
                $this->singleton($key, $value);
            }
        }

        $provider->callBootedCallbacks();
    }

    /**
     * Bind aliases with contracts
     */
    public function registerCoreContainerAliases(): void
    {
        foreach ([
            'auth' => [
                \Illuminate\Auth\AuthManager::class,
                \Illuminate\Contracts\Auth\Factory::class
            ],
            'auth.driver' => [
                \Illuminate\Contracts\Auth\Guard::class
            ],
            'cookie' => [
                \Illuminate\Cookie\CookieJar::class,
                \Illuminate\Contracts\Cookie\Factory::class,
                \Illuminate\Contracts\Cookie\QueueingFactory::class
            ],
            'app' => [
                self::class,
                \Illuminate\Contracts\Container\Container::class,
                \Psr\Container\ContainerInterface::class
            ],
            'config' => [
                \Illuminate\Config\Repository::class,
                \Illuminate\Contracts\Config\Repository::class
            ],
            'cache' => [
                \Illuminate\Cache\CacheManager::class,
                \Illuminate\Contracts\Cache\Factory::class
            ],
            'cache.store' => [
                \Illuminate\Cache\Repository::class,
                \Illuminate\Contracts\Cache\Repository::class,
                \Psr\SimpleCache\CacheInterface::class
            ],
            'db' => [
                \Illuminate\Database\DatabaseManager::class,
                \Illuminate\Database\ConnectionResolverInterface::class
            ],
            'db.connection' => [
                \Illuminate\Database\Connection::class,
                \Illuminate\Database\ConnectionInterface::class
            ],
            'db.factory' => [
                \Illuminate\Database\Connectors\ConnectionFactory::class,
            ],
            'files' => [
                \Illuminate\Filesystem\Filesystem::class
            ],
            'filesystem' => [
                \Illuminate\Filesystem\FilesystemManager::class,
                \Illuminate\Contracts\Filesystem\Factory::class
            ],
            'filesystem.disk' => [
                \Illuminate\Contracts\Filesystem\Filesystem::class
            ],
            'filesystem.cloud' => [
                \Illuminate\Contracts\Filesystem\Cloud::class
            ],
            'maps' => [
                MapContainer::class,
                MapContainer::class
            ],
            'events' => [
                \Illuminate\Events\Dispatcher::class,
                \Illuminate\Contracts\Events\Dispatcher::class
            ],
            'queue' => [
                \Illuminate\Queue\QueueManager::class,
                \Illuminate\Contracts\Queue\Factory::class,
                \Illuminate\Contracts\Queue\Monitor::class
            ],
            'queue.connection' => [
                \Illuminate\Contracts\Queue\Queue::class
            ],
            'queue.failer' => [
                \Illuminate\Queue\Failed\FailedJobProviderInterface::class
            ],
            'log' => [
                \Illuminate\Log\LogManager::class,
                \Psr\Log\LoggerInterface::class
            ],
            'router' => [
                \Illuminate\Routing\Router::class,
                \Illuminate\Contracts\Routing\Registrar::class,
                \Illuminate\Contracts\Routing\BindingRegistrar::class
            ],
            'url' => [
                \Illuminate\Routing\UrlGenerator::class,
                \Illuminate\Contracts\Routing\UrlGenerator::class
            ],
            'validator' => [
                \Illuminate\Validation\Factory::class,
                \Illuminate\Contracts\Validation\Factory::class
            ],
            'Request' => [
                \Illuminate\Support\Facades\Request::class
            ],
            'Response' => [
                \Illuminate\Support\Facades\Response::class
            ],
            'Route' => [
                \Illuminate\Support\Facades\Route::class
            ],
            'encrypter' => [
                \Illuminate\Encryption\Encrypter::class,
                \Illuminate\Contracts\Encryption\Encrypter::class,
                \Illuminate\Contracts\Encryption\StringEncrypter::class,
            ],
        ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    /**
     * Bind and load container configurations
     * usage from Facade, see Illuminate\Support\Facades\Config
     */
    protected function loadConfiguration(): void
    {
        $items = [];
        $_request = Application::get()->getRequest();

        // App
        $items['app'] = [
            'key' => PKPAppKey::getKey(),
            'cipher' => PKPAppKey::getCipher(),
            'timezone' => Config::getVar('general', 'timezone', 'UTC'),
            'env' => Config::getVar('general', 'app_env', 'production'),
        ];

        // Database connection
        $driver = static::getDatabaseDriverName();
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

        // Auth
        $items['auth'] = [
            'defaults' => [
                'guard' => 'web',
            ],
            'guards' => [
                'web' => [
                    'driver' => 'session',
                    'provider' => 'users',
                ],
            ],
            'providers' => [
                'users' => [
                    'driver' => PKPUserProvider::AUTH_PROVIDER,
                ],
            ],
        ];

        // Session manager
        $items['session'] = [
            'driver' => 'database',
            'table' => 'sessions',
            'cookie' => Config::getVar('general', 'session_cookie_name'),
            'path' => Config::getVar('general', 'session_cookie_path', $_request->getBasePath() . '/'),
            'domain' => $_request->getServerHost(false, false) ?: 'localhost', // FIXME: Do not store default early in bootstrap
            'secure' => Config::getVar('security', 'force_ssl', false),
            'lifetime' => Config::getVar('general', 'session_lifetime', 30) * 24 * 60, // lifetime need to set in minutes
            'lottery' => [2, 100],
            'expire_on_close' => false,
            'same_site' => Config::getVar('general', 'session_samesite', 'lax'),
            'partitioned' => false,
            'encrypt' => false,
            'cookie_encryption' => Config::getVar('security', 'cookie_encryption'),
        ];


        // Queue connection
        $items['queue']['default'] = 'database';
        $items['queue']['connections']['sync']['driver'] = 'sync';
        $items['queue']['connections']['database'] = [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 240,
            'after_commit' => true,
        ];
        $items['queue']['failed'] = [
            'driver' => 'database',
            'database' => $driver,
            'table' => 'failed_jobs',
        ];

        // Logging
        $items['logging']['channels']['errorlog'] = [
            'driver' => 'errorlog',
            'level' => 'debug',
        ];

        // Mail Service
        $items['mail']['mailers']['sendmail'] = [
            'transport' => 'sendmail',
            'path' => Config::getVar('email', 'sendmail_path'),
        ];
        $items['mail']['mailers']['smtp'] = [
            'transport' => 'smtp',
            'host' => Config::getVar('email', 'smtp_server'),
            'port' => Config::getVar('email', 'smtp_port'),
            'encryption' => Config::getVar('email', 'smtp_auth'),
            'username' => Config::getVar('email', 'smtp_username'),
            'password' => Config::getVar('email', 'smtp_password'),
            'verify_peer' => !Config::getVar('email', 'smtp_suppress_cert_check'),
        ];
        $items['mail']['mailers']['log'] = [
            'transport' => 'log',
            'channel' => 'errorlog',
        ];
        $items['mail']['mailers']['phpmailer'] = [
            'transport' => 'phpmailer',
        ];

        $items['mail']['default'] = static::getDefaultMailer();

        // Cache configuration
        $items['cache'] = [
            'default' => 'opcache',
            'stores' => [
                'opcache' => [
                    'driver' => 'opcache',
                    'path' => Core::getBaseDir() . '/cache/opcache'
                ]
            ]
        ];

        // Create instance and bind to use globally
        $this->instance('config', new Repository($items));
    }

    /**
     * @see Illuminate\Foundation\Application::basePath
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? "/{$path}" : $path);
    }

    /**
     * Alias of basePath(), Laravel app path differs from installation path
     */
    public function path(string $path = ''): string
    {
        return $this->basePath($path);
    }

    /**
     * Retrieves default mailer driver depending on the configuration
     *
     * @throws Exception
     */
    protected static function getDefaultMailer(): string
    {
        $default = Config::getVar('general', 'sandbox', false)
            ? 'log'
            : Config::getVar('email', 'default');

        if (!$default) {
            throw new Exception('Mailer driver isn\'t specified in the application\'s config');
        }

        return $default;
    }

    /**
     * Setting a proxy on the stream_context_set_default when configuration [proxy] is filled
     */
    protected function settingProxyForStreamContext(): void
    {
        $proxy = new ProxyParser();

        if ($httpProxy = Config::getVar('proxy', 'http_proxy')) {
            $proxy->parseFQDN($httpProxy);
        }

        if ($httpsProxy = Config::getVar('proxy', 'https_proxy')) {
            $proxy->parseFQDN($httpsProxy);
        }

        if ($proxy->isEmpty()) {
            return;
        }

        /**
         * `Connection close` here its to avoid slowness. More info at https://www.php.net/manual/en/context.http.php#114867
         * `request_fulluri` its related to avoid proxy errors. More info at https://www.php.net/manual/en/context.http.php#110449
         */
        $opts = [
            'http' => [
                'protocol_version' => 1.1,
                'header' => [
                    'Connection: close',
                ],
                'proxy' => $proxy->getProxy(),
                'request_fulluri' => true,
            ],
        ];

        if ($proxy->getAuth()) {
            $opts['http']['header'][] = 'Proxy-Authorization: Basic ' . $proxy->getAuth();
        }

        $context = stream_context_create($opts);
        stream_context_set_default($opts);
        libxml_set_streams_context($context);
    }

    /**
     * Override Laravel method; always false.
     * Prevents the undefined method error when the Log Manager tries to determine the driver
     */
    public function runningUnitTests(): bool
    {
        return false;
    }

    /**
     * Determine if the application is currently down for maintenance.
     */
    public function isDownForMaintenance(): bool
    {
        return Application::isUnderMaintenance();
    }

    /**
     * Determine if the application is running in the console.
     */
    public function runningInConsole(?string $scriptPath = null): bool
    {
        if (strtolower(php_sapi_name() ?: '') === 'cli') {
            return true;
        }

        if (!$scriptPath) {
            return false;
        }

        if (mb_stripos($_SERVER['SCRIPT_NAME'] ?? '', $scriptPath) !== false) {
            return true;
        }
        
        if (mb_stripos($_SERVER['SCRIPT_FILENAME'] ?? '', $scriptPath) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Get or check the current application environment.
     */
    public function environment(string ...$environments): string|bool
    {
        if (count($environments) > 0) {
            return Str::is($environments, $this->get('config')['app']['env']);
        }

        return $this->get('config')['app']['env'];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPContainer', '\PKPContainer');
}
