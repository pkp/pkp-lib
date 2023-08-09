<?php

namespace PKP\core;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Routing\RoutingServiceProvider;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Contracts\Routing\UrlGenerator as UrlGeneratorContract;
use PKP\core\PKPContainer;
use PKP\middleware\HasUser;
use PKP\middleware\HasRoles;
use PKP\middleware\HasContext;
use PKP\middleware\AllowCrossOrigin;
use PKP\middleware\ValidateCsrfToken;
use Psr\Http\Message\ServerRequestInterface;
use PKP\middleware\DecodeApiTokenWithValidation;
use PKP\middleware\SetupContextBasedOnRequestUrl;


class PKPRoutingProvider extends RoutingServiceProvider
{
    protected static $globalMiddleware = [
        AllowCrossOrigin::class,
        SetupContextBasedOnRequestUrl::class,
        DecodeApiTokenWithValidation::class,
        ValidateCsrfToken::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware.
     * These middleware can/should be assigned to specific routes or routes groups individually.
     */
    protected array $routeMiddleware = [
        'has.roles'     => HasRoles::class,
        'has.user'      => HasUser::class,
        'has.context'   => HasContext::class,
    ];

    public static function getGlobalRouteMiddleware(): array
    {
        return self::$globalMiddleware;
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        parent::register();

        $this->registerRouteMiddleware();
        $this->registerRoutePatterns();
        $this->registerResponseBindings();
    }

    public function registerRouter(): void
    {
        $this->app->singleton('router', function ($app) {
            $request = Request::capture();
            $app->instance(\Illuminate\Http\Request::class, $request);
            return new Router($app['events'], $app);
        });
    }

    public function registerRouteMiddleware(): void
    {
        $router = app('router'); /** @var \Illuminate\Routing\Router $router */

        foreach ($this->routeMiddleware as $key => $middleware) {
            $router->aliasMiddleware($key, $middleware);
        }
    }

    public function registerRoutePatterns(): void
    {
        $router = app('router'); /** @var \Illuminate\Routing\Router $router */

        $router->pattern('contextPath', '(.*?)');
        $router->pattern('version', '(.*?)');
    }

    protected function registerResponseBindings(): void
    {
        $container = PKPContainer::getInstance();

        $container->bind(\Illuminate\Routing\RouteCollectionInterface::class, \Illuminate\Routing\RouteCollection::class);
        $container->bind(
            \Illuminate\View\ViewFinderInterface::class, 
            fn ($app) => new \Illuminate\View\FileViewFinder(app(\Illuminate\Filesystem\Filesystem::class), [])
        );
        $container->bind(\Illuminate\Contracts\View\Factory::class, \Illuminate\View\Factory::class);
        $container->bind(\Illuminate\Contracts\Routing\ResponseFactory::class, \Illuminate\Routing\ResponseFactory::class);
    }

    /**
     * Register the URL generator service.
     *
     * @return void
     */
    protected function registerUrlGenerator()
    {
        $this->app->singleton('url', function ($app) {
            $routes = $app['router']->getRoutes();

            // The URL generator needs the route collection that exists on the router.
            // Keep in mind this is an object, so we're passing by references here
            // and all the registered routes will be available to the generator.
            $app->instance('routes', $routes);

            return new UrlGenerator(
                $routes, 
                Request::capture(), 
                $app['config']['app.asset_url']
            );
        });

        $this->app->singleton(UrlGeneratorContract::class, function($app) {
            return $app['url'];
        });

        $this->app->extend('url', function (UrlGeneratorContract|UrlGenerator $url, $app) {
            // Next we will set a few service resolvers on the URL generator so it can
            // get the information it needs to function. This just provides some of
            // the convenience features to this URL generator like "signed" URLs.
            $url->setSessionResolver(function () {
                return $this->app['session'] ?? null;
            });

            $url->setKeyResolver(function () {
                return $this->app->make('config')->get('app.key');
            });

            // If the route collection is "rebound", for example, when the routes stay
            // cached for the application, we will need to rebind the routes on the
            // URL generator instance so it has the latest version of the routes.
            $app->rebinding('routes', function ($app, $routes) {
                $app['url']->setRoutes($routes);
            });

            return $url;
        });
    }

    /**
     * Register a binding for the PSR-7 request implementation.
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function registerPsrRequest()
    {
        $this->app->bind(ServerRequestInterface::class, function ($app) {
            if (class_exists(Psr17Factory::class) && class_exists(PsrHttpFactory::class)) {
                $psr17Factory = new Psr17Factory;

                return (new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory))
                    ->createRequest($app->make(\Illuminate\Http\Request::class));
            }

            throw new BindingResolutionException('Unable to resolve PSR request. Please install the symfony/psr-http-message-bridge and nyholm/psr7 packages.');
        });
    }
}