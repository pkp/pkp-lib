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
use Illuminate\Foundation\Console\Kernel;
use Illuminate\Http\Response;
use Illuminate\Log\LogServiceProvider;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use PKP\config\Config;
use PKP\i18n\LocaleServiceProvider;
use PKP\proxy\ProxyParser;
use Throwable;

class PKPContainer extends Container
{
    /** Normalized database driver identifiers (Laravel connection drivers). */
    public const DRIVER_MYSQL = 'mysql';
    public const DRIVER_MARIADB = 'mariadb';
    public const DRIVER_POSTGRES = 'pgsql';

    /** Database charset/collation policy. See pkp/pkp-lib#11563. */
    public const CHARSET_UTF8 = 'utf8';
    public const CHARSET_UTF8MB4 = 'utf8mb4';
    public const LEGACY_COLLATION = 'utf8_general_ci';

    /**
     * Define if the app currently runing the unit test
     */
    private bool $isRunningUnitTest = false;

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
            return static::DRIVER_POSTGRES;
        }

        return match ($driver) {
            'mysql', 'mysqli' => static::DRIVER_MYSQL,
            'mariadb' => static::DRIVER_MARIADB
        };
    }

    /**
     * Derive the database connection charset (and collation) from the single
     * source of truth: the `[database] collation` setting. See pkp/pkp-lib#11563.
     *
     * The collation drives everything:
     *   - a `utf8mb4_*` collation               => `utf8mb4` charset
     *   - a `utf8_*` or `utf8mb3_*` collation    => `utf8` charset (legacy)
     *   - any non-UTF-8 collation (e.g. latin1_*) => rejected with an Exception
     *
     * PostgreSQL has no per-connection collation, so the collation is always null
     * and the charset is always `utf8`.
     *
     * The default collation is intentionally the legacy `utf8_general_ci`: a site
     * upgrading without adding the new setting keeps its previous behaviour, which
     * avoids silently mixing utf8mb3 and utf8mb4 tables. New installs receive
     * `utf8mb4_unicode_ci` from config.TEMPLATE.inc.php.
     *
     * @param ?string $driver    Normalized driver (mysql|mariadb|pgsql); resolved from config when null.
     * @param ?string $collation Configured collation; read from config when null.
     *
     * @throws Exception When the collation is not a UTF-8 collation (utf8mb4_* or utf8_*)
     *
     * @return array{charset: string, collation: ?string}
     */
    public static function getDatabaseCharsetCollation(?string $driver = null, ?string $collation = null): array
    {
        $driver ??= static::getDatabaseDriverName();

        // PostgreSQL: no per-connection collation; charset is always utf8.
        if ($driver === static::DRIVER_POSTGRES) {
            return ['charset' => static::CHARSET_UTF8, 'collation' => null];
        }

        $collation ??= Config::getVar('database', 'collation', static::LEGACY_COLLATION);
        $normalizedCollation = strtolower((string) $collation);
        
        if (str_starts_with($normalizedCollation, static::CHARSET_UTF8MB4)) {
            $charset = static::CHARSET_UTF8MB4;
        } elseif (str_starts_with($normalizedCollation, static::CHARSET_UTF8)) {
            // utf8_*, utf8mb3_*, or plain utf8 — all map to the utf8 charset.
            $charset = static::CHARSET_UTF8;
        } else {
            // Anything else (e.g. latin1_*, ascii_*) is unsupported: Application stores UTF-8 data.
            // Fail fast with a clear message instead of letting the database raise an obscure
            // "COLLATION ... is not valid for CHARACTER SET ..." error. See pkp/pkp-lib#11563.
            throw new Exception("Unsupported database collation '{$collation}'. Application requires a UTF-8 collation: use utf8mb4_unicode_ci (recommended) or utf8_general_ci (legacy).");
        }

        return ['charset' => $charset, 'collation' => $collation];
    }

    /**
     * Build the charset/collation portion of a database connection config from the
     * single [database] collation setting. See pkp/pkp-lib#11563.
     *
     * Returned as a ready-to-merge array so callers do not repeat the "set charset,
     * conditionally set collation" logic. PostgreSQL has no per-connection collation,
     * so the collation key is omitted entirely (rather than set to null) for it.
     *
     * @param ?string $driver Normalized driver; resolved from config when null.
     *
     * @return array{charset: string, collation?: string}
     */
    public static function getDatabaseConnectionCharsetConfig(?string $driver = null): array
    {
        $charsetCollation = static::getDatabaseCharsetCollation($driver);

        return $charsetCollation['collation'] === null
            ? ['charset' => $charsetCollation['charset']]
            : ['charset' => $charsetCollation['charset'], 'collation' => $charsetCollation['collation']];
    }

    /**
     * Return a non-blocking warning about a database charset/encoding problem, or null
     * when there is nothing to warn about. A single entry point handles both database
     * families. See pkp/pkp-lib#11563.
     *
     * - MySQL/MariaDB: the charset lives per table, so warn when the configured charset
     *   (derived from the [database] collation) differs from the charset of the existing
     *   tables — i.e. the database would end up with a mix of charsets, which can later
     *   cause "Illegal mix of collations" errors when joining old and new tables.
     * - PostgreSQL: the charset is the (immutable) database encoding, not a per-table
     *   property, so warn when the database itself is not UTF8
     *
     * Advisory only: any failure in the inspection (or the absence of existing tables,
     * e.g. a fresh install) returns null and never blocks install or upgrade.
     *
     * @param ?string $driver Normalized driver (mysql|mariadb|pgsql); resolved from config when null.
     */
    public static function getDatabaseCharsetWarning(?string $driver = null): ?string
    {
        $driver ??= static::getDatabaseDriverName();

        try {
            // PostgreSQL: warn when the database encoding is not UTF8.
            if ($driver === static::DRIVER_POSTGRES) {
                $row = DB::selectOne('SELECT pg_encoding_to_char(encoding) AS encoding FROM pg_database WHERE datname = current_database()');
                $encoding = strtoupper($row->encoding ?? '');
                if ($encoding === '' || $encoding === strtoupper(static::CHARSET_UTF8)) {
                    return null;
                }

                return "WARNING: The PostgreSQL database uses '{$encoding}' encoding, but the application requires UTF8."
                    . " Characters outside '{$encoding}' (e.g. emoji or many non-Latin scripts) cannot be stored"
                    . ' and will raise errors. The database encoding is set when the database is created and is not'
                    . " changed by the application; recreate the database with UTF8 (CREATE DATABASE ... ENCODING 'UTF8') and"
                    . ' reload your data. See pkp/pkp-lib#11563.';
            }

            // MySQL/MariaDB: warn when the configured charset differs from the charset of the
            // existing tables. MySQL stores a collation per table, so join through
            // COLLATION_CHARACTER_SET_APPLICABILITY to resolve each table's charset.
            $charsetCollation = static::getDatabaseCharsetCollation($driver);
            $configuredCharset = $charsetCollation['charset'];

            $existing = DB::table('information_schema.TABLES as t')
                ->join(
                    'information_schema.COLLATION_CHARACTER_SET_APPLICABILITY as ccsa',
                    'ccsa.COLLATION_NAME',
                    '=',
                    't.TABLE_COLLATION'
                )
                ->where('t.TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->where('t.TABLE_TYPE', 'BASE TABLE')
                ->distinct()
                ->pluck('ccsa.CHARACTER_SET_NAME')
                ->all();

            // MySQL 8 reports the utf8 charset as 'utf8mb3'; treat both as 'utf8'.
            $existing = array_values(array_unique(str_replace('utf8mb3', static::CHARSET_UTF8, $existing)));

            $differing = array_diff($existing, [$configuredCharset]);
            if (empty($differing)) {
                return null;
            }

            $existingList = implode(', ', $existing);
            $collation = $charsetCollation['collation'];
            $message = "WARNING: The database connection is configured for charset '{$configuredCharset}'"
                . " (collation '{$collation}'), but existing tables use charset(s): {$existingList}."
                . " New tables created by the application will use '{$configuredCharset}', so the database will contain"
                . " a mix of charsets. This can cause 'Illegal mix of collations' errors in future operations"
                . ' that join old and new tables. Existing tables are never converted automatically. To resolve'
                . ' this, either convert each existing table, e.g.'
                . " ALTER TABLE <table> CONVERT TO CHARACTER SET {$configuredCharset} COLLATE {$collation};"
                . ' or set [database] collation in config.inc.php to match your existing tables.';

            // A non-UTF-8 existing charset (e.g. latin1) may actually hold UTF-8 data
            // stored under the wrong label. A blind CONVERT TO re-encodes the bytes and would
            // double-encode such data, so the admin must check the content first. See pkp/pkp-lib#11563.
            $nonUtf8 = array_diff($existing, [static::CHARSET_UTF8, static::CHARSET_UTF8MB4]);
            if (!empty($nonUtf8)) {
                $message .= ' NOTE: some existing tables use a non-UTF-8 charset; before converting,'
                    . ' verify whether their data is genuinely that charset or UTF-8 stored under the wrong'
                    . ' label, because a blind CONVERT TO can double-encode mislabelled data.';
            }

            return $message . ' See pkp/pkp-lib#11563.';
        } catch (Throwable $e) {
            // Advisory only: never block install/upgrade if the inspection fails.
            return null;
        }
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
        $this->register(new \Illuminate\Log\Context\ContextServiceProvider($this));
        $this->register(new \Illuminate\Database\DatabaseServiceProvider($this));
        $this->register(new \Illuminate\Bus\BusServiceProvider($this));
        $this->register(new PKPQueueProvider($this));
        $this->register(new MailServiceProvider($this));
        $this->register(new LocaleServiceProvider($this));
        $this->register(new PKPRoutingProvider($this));
        $this->register(new InvitationServiceProvider($this));
        $this->register(new ScheduleServiceProvider($this));
        $this->register(new ConsoleCommandServiceProvider($this));
        $this->register(new \PKP\core\ValidationServiceProvider($this));
        $this->register(new \Illuminate\Foundation\Providers\FormRequestServiceProvider($this));
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

        // Database connection. Charset/collation derive from the single [database]
        // collation setting (pkp/pkp-lib#11563).
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
        ] + static::getDatabaseConnectionCharsetConfig($driver);

        // Auth
        // remember_me_lifetime is the "remember me" persistent-login cookie duration in days
        // (fractional allowed). It is converted to minutes and to extend login beyond the
        // idle session as it should be >= session_lifetime.
        $rememberMeMinutes = max(1, (int) round((float) Config::getVar('security', 'remember_me_lifetime', 30) * 24 * 60));
        $items['auth'] = [
            'defaults' => [
                'guard' => 'web',
            ],
            'guards' => [
                'web' => [
                    'driver' => 'session',
                    'provider' => 'users',
                    'remember' => $rememberMeMinutes, // remember-me cookie lifetime in minutes
                ],
            ],
            'providers' => [
                'users' => [
                    'driver' => PKPUserProvider::AUTH_PROVIDER,
                ],
            ],
        ];

        // Session manager
        // session_lifetime is in days; fractional values are allowed (e.g. 0.5 = 12 hours, 0.0833 ≈ 2 hours).
        // It is converted to minutes and clamped to a minimum of 1 minute so Laravel never receives 0
        // (which it would treat as "immediately expired").
        // For browser-close session expiration, use session_expire_on_close = On in config.inc.php
        $sessionLifetimeMinutes = max(1, (int) round((float) Config::getVar('general', 'session_lifetime', 7) * 24 * 60));

        $items['session'] = [
            'driver' => 'database',
            'table' => 'sessions',
            'cookie' => Config::getVar('general', 'session_cookie_name'),
            'path' => Config::getVar('general', 'session_cookie_path', $_request->getBasePath() . '/'),
            'domain' => $_request->getServerHost(false, false) ?: 'localhost', // FIXME: Do not store default early in bootstrap
            'secure' => Config::getVar('security', 'force_ssl', false),
            'lifetime' => $sessionLifetimeMinutes, // lifetime in minutes
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
     * Determine if the application is in the production environment.
     */
    public function isProduction(): bool
    {
        return Config::getVar('general', 'app_env', 'production') === 'production';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPContainer', '\PKPContainer');
}
