<?php

/**
 * @file classes/core/PKPContainer.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContainer
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
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Console\Kernel;
use Illuminate\Http\Response;
use Illuminate\Log\LogServiceProvider;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Laravel\Scout\EngineManager;
use PKP\config\Config;
use PKP\i18n\LocaleServiceProvider;
use PKP\proxy\ProxyParser;
use Throwable;

class PKPContainer extends Container
{
    /**
     * Define if the app currently runing the unit test
     */
    private bool $isRunningUnitTest = false;

    /**
     * The base path of the application, needed for base_path helper
     */
    protected string $basePath;

    /**
     * Application strict mode control
     */
    protected bool $strictMode;

    /**
     * Create own container instance, initialize bindings
     */
    public function __construct()
    {
        $this->basePath = BASE_SYS_DIR;
        $this->settingProxyForStreamContext();
        $this->registerBaseBindings();
        $this->registerCoreContainerAliases();
        $this->registerClassAliases();

        $this->setApplicationStrictModeStatus(
            // PHPUnit tests force strict mode to be enabled
            // This takes precedence over config settings
            defined('PKP_PHPUNIT_STRICT_MODE') && PKP_PHPUNIT_STRICT_MODE === true
                ? true
                : Config::getVar('general', 'strict', true)
        );
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
        $this->instance('path.config', "{$this->basePath}/config"); // Necessary for Scout to let CLI happen
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

                    if ($pkpRouter instanceof APIRouter && app('router')->getRoutes()->count()) {
                        if ($exception instanceof \Illuminate\Validation\ValidationException) {
                            return response()
                                ->json($exception->errors(), $exception->status);
                        }

                        return response()->json(
                            [
                                'error' => $exception->getMessage()
                            ],
                            in_array($exception->getCode(), array_keys(Response::$statusTexts))
                            ? $exception->getCode()
                            : Response::HTTP_INTERNAL_SERVER_ERROR
                        );
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

        $this->singleton(
            'jobRunner',
            fn ($app) => \PKP\queue\JobRunner::getInstance($app['pkpJobQueue'])
        );

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
        $this->register(new PKPEncryptionServiceProvider($this));
        $this->register(new PKPAuthServiceProvider($this));
        $this->register(new \Illuminate\Cookie\CookieServiceProvider($this));
        $this->register(new PKPSessionServiceProvider($this));
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
        $this->register(new ValidationServiceProvider($this));
        $this->register(new \Illuminate\Foundation\Providers\FormRequestServiceProvider($this));
        $this->register(new \Laravel\Scout\ScoutServiceProvider($this));
        $this->register(new PKPBladeViewServiceProvider($this));
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
            'view' => [
                \Illuminate\Support\Facades\View::class,
            ],
        ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    /**
     * Register class aliases to simplify the usage of the class
     * To register more aliases, as AliasLoader::getInstance()->alias('key', SomeClass::class)
     */
    protected function registerClassAliases(): void
    {
        $aliases = [
            'Str' => \Illuminate\Support\Str::class,
            'Arr' => \Illuminate\Support\Arr::class,
        ];

        AliasLoader::getInstance($aliases)->register();
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
            'expire_on_close' => Config::getVar('security', 'session_expire_on_close', false),
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
            'retry_after' => 610,
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
            'default' => Config::getVar('cache', 'default', 'file'),
            'stores' => [
                'opcache' => [
                    'driver' => 'opcache',
                    'path' => Config::getVar('cache', 'path', Core::getBaseDir() . '/cache/opcache')
                ],
                'file' => [
                    'driver' => 'file',
                    'path' => Config::getVar('cache', 'path', Core::getBaseDir() . '/cache/opcache')
                ]
            ]
        ];

        $items['scout'] = [
            'driver' => Config::getVar('search', 'driver', 'database'),
        ];

        // Blade/Smarty view settings
        // Resolution happens in Factory.make() via View::resolveName hook
        $items['view'] = [
            'compiled' => Str::of(
                Config::getVar('cache', 'compiled', Core::getBaseDir() . '/cache/opcache')
            )->beforeLast('/')->append('/t_compile')->value(),
            'cache' => true, // Cache compiled templates (set false only for debugging)
            'compiled_extension' => 'php',
            'relative_hash' => false,
            'paths' => [
                // 'app' namespace includes both directories to match Smarty's app: resource
                'app' => [$this->basePath('templates'), $this->basePath('lib/pkp/templates')],
                'pkp' => $this->basePath('lib/pkp/templates'),
            ],
            'components' => [
                // Component namespace here registered based on hierarchy and priority,
                // do not alter the ordering
                'namespace' => [
                    'app' => Application::get()->getNamespace() . PKPBladeViewServiceProvider::VIEW_NAMESPACE_PATH,
                    'pkp' => $this->getNamespace() . PKPBladeViewServiceProvider::VIEW_NAMESPACE_PATH,
                ],
            ]
        ];

        // Create instance and bind to use globally
        $this->instance('config', new Repository($items));

        app()->extend(EngineManager::class, fn (EngineManager $s) => $s->extend('opensearch', fn () => new \PKP\search\engines\OpenSearchEngine()));
        app()->extend(EngineManager::class, fn (EngineManager $s) => $s->extend('database', fn () => new \PKP\search\engines\DatabaseEngine()));
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
            throw new Exception('The mailer driver isn\'t specified in the config.inc.php configuration file. See the "default" setting in the [email] configuration. Configuration details are available in the config.TEMPLATE.inc.php template.');
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

    /**
     * Override Laravel method; always false.
     * Prevents the undefined method error when the Log Manager tries to determine the driver
     */
    public function runningUnitTests(): bool
    {
        return $this->isRunningUnitTest;
    }

    /**
     * Set the app running unit test
     */
    public function setRunningUnitTests(): void
    {
        $this->isRunningUnitTest = true;
    }

    /**
     * Unset the app running unit test
     */
    public function unsetRunningUnitTests(): void
    {
        $this->isRunningUnitTest = false;
    }

    /*
     * Set application strict mode
     */
    public function setApplicationStrictModeStatus(bool $mode): void
    {
        $this->strictMode = $mode;
    }

    /*
     * Get application strict mode
     */
    public function getApplicationStrictModeStatus(): bool
    {
        return $this->strictMode;
    }

    /**
     * Flush the output buffer to ensure all output is sent to the client before any background
     * task processing (e.g. job processing, schedule task running) to avoid any potential output
     * buffering issues which may cause client page load time and performance degradation.
     * 
     * This should be called before the background tasks starts to process and only when there are
     * background tasks available to process. 
     */
    public function flushOutputBuffer(): void
    {
        // Disable flushing output buffer for unit tests as PHPUnit is quite sensitive to output buffer 
        // manipulation during tests. The root cause is The PHPUnit configuration has
        // `beStrictAboutOutputDuringTests="true"` which makes PHPUnit very sensitive to any output
        // buffer manipulation during tests and mark it as risky.
        if ($this->runningUnitTests()) {
            return;
        }

        // Force flush and close connection for non-blocking behavior
        // and set headers to close connection and specify content length (if buffer exists)
        if (!headers_sent()) {
            header('Connection: close');
            header('Content-Encoding: none');
            if (ob_get_length() > 0) {
                header('Content-Length: ' . ob_get_length());
            }
        }

        // Flush output buffer and send response and allow script to continue if client disconnects.
        // Flush and end ALL output buffer levels (if started) and also the system buffer.
        ignore_user_abort(true);
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();

        // For PHP-FPM (Nginx/Apache with FPM): Explicitly finish FastCGI request
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * Get the application namespace.
     */
    public function getNamespace(): string
    {
        return 'PKP\\';
    }

    /**
     * Determine if the application is in the production environment.
     */
    public function isProduction(): bool
    {
        return Config::getVar('general', 'app_env', 'production') === 'production';
    }

    /**
     * Register class consts as global consts
     */
    public function registerGlobalConstants(string $classNamespacePath, array $constants): void
    {
        if ($this->getApplicationStrictModeStatus()) {
            throw new Exception('Registering class const as global const in strict mode is not allowed');
        }

        if (!class_exists($classNamespacePath)) {
            throw new Exception(sprintf('Given class %s does not exist', $classNamespacePath));
        }

        foreach ($constants as $constant) {
            if (!defined($classNamespacePath . '::' . $constant)) {
                throw new Exception(sprintf('Constant %s is not defined for class %s', $constant, $classNamespacePath));
            }

            if (!defined($constant)) {
                define($constant, constant($classNamespacePath . '::' . $constant));
            }
        }
    }

    /**
     * Encrypt the given value using app key
     */
    public function encrypt(mixed $value): string
    {
        return Crypt::encrypt($value);
    }

    /**
     * Decrypt the given encrypted value using app key
     */
    public function decrypt(string $value): mixed
    {
        try {
            return Crypt::decrypt($value);
        } catch (Throwable $e) {
            error_log("Unable to decrypt the {$value} with exception : {$e->__toString()}");
        }

        return null;
    }
}
