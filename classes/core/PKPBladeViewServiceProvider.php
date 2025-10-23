<?php

namespace PKP\core;

use Illuminate\View\FileViewFinder;
use Illuminate\View\ViewServiceProvider;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\View\Compilers\BladeCompiler as IlluminateBladeCompiler;
use PKP\core\PKPContainer;
use PKP\core\blade\BladeCompiler;
use PKP\core\blade\DynamicComponent;
use Illuminate\Foundation\AliasLoader;

class PKPBladeViewServiceProvider extends ViewServiceProvider
{
    public const VIEW_NAMESPACE_PATH = 'view\\components\\';

    /**
     * Boot the service provider
     *
     * @return void
     */
    public function boot()
    {
        // Allow to render blade files as .blade e.g. with the .php extension
        // but still allow to render views as .blade.php to accommodate default behavior.
        View::addExtension('blade', 'blade');

        AliasLoader::getInstance()->alias('Js', \Illuminate\Support\Js::class);

        // This allows templates to be referenced explicitly, 
        // e.g., @include('VIEW_NAMESPACE::some-template') or @include('VIEW_NAMESPACE::some-template'),
        // or even allow to render view as view('VIEW_NAMESPACE::some-template', [....])
        // which allow to render views from any namespace ambiguity and improving maintainability.
        collect($this->app->get('config')->get('view.paths'))
            ->each(fn ($path, $namespace) => view()->addNamespace($namespace, $path));
        
        // This allows to render components as <x-COMPONENT_NAMESPACE::some-component />
        // which allow to render components from any namespace ambiguity and improving maintainability.
        collect($this->app->get('config')->get('view.components.namespace'))
            ->each(fn ($namespace, $prefix) => Blade::componentNamespace($namespace, $prefix));

        // Use this macro to resolve the view path for a plugin class based component in the component class
        // in the render method as `View:resolvePluginComponentViewPath($this, 'COMPONENTS_VIEW_PATH')`
        View::macro(
            'resolvePluginComponentViewPath',
            function (\Illuminate\View\Component $component, string $viewPath): string {
                $bladeCompiler = app()->get('blade.compiler'); /** @var \PKP\core\blade\BladeCompiler $bladeCompiler */
                $classComponentNamespaces = $bladeCompiler->getClassComponentNamespaces();

                $className = get_class($component);
                $componentNamespace = substr($className, 0, strrpos($className, '\\'));
                $pluginViewNamespace = array_search($componentNamespace, $classComponentNamespaces);

                return "{$pluginViewNamespace}::{$viewPath}";
            }
        );
        
        // use as @loadScript(['context' => 'frontend'])
        Blade::directive('loadScript', function ($parameters) {
            return "<?php
                echo \PKP\\template\\PKPTemplateManager::getManager()->smartyLoadScript($parameters);
            ?>";
        });

        // use as @loadStylesheet(['context' => 'frontend'])
        Blade::directive('loadStylesheet', function ($parameters) {
            return "<?php
                echo \PKP\\template\\PKPTemplateManager::getManager()->smartyLoadStylesheet($parameters);
            ?>";
        });

        // use as @loadHeader(['context' => 'frontend'])
        Blade::directive('loadHeader', function ($parameters) {
            return "<?php
                echo \PKP\\template\\PKPTemplateManager::getManager()->smartyLoadHeader($parameters);
            ?>";
        });

        // use as @loadMenu(['name' => 'user', 'id' => 'navigationUser', 'ulClass' => 'pkp_navigation_user', 'liClass' => 'profile'])
        Blade::directive('loadMenu', function ($parameters) {
            return "<?php
                echo \PKP\\template\\PKPTemplateManager::getManager()->smartyLoadNavigationMenuArea($parameters);
            ?>";
        });

        // use as @callHook(['name' => 'Templates::Common::Footer::PageFooter'])
        Blade::directive('callHook', function ($parameters) {
            return "<?php
                echo \PKP\\template\\PKPTemplateManager::getManager()->smartyCallHook($parameters);
            ?>";
        });

        Blade::directive('runHook', function ($parameters) {
            return "<?php
                \PKP\\template\\PKPTemplateManager::getManager()->smartyRunHook($parameters);
            ?>";
        });

        // use as @html_select_date_a11y(['legend' => $dateFromLegend, 'prefix' => 'dateFrom', 'time' => $dateFrom, 'start_year' => $yearStart, 'end_year' => $yearEnd])
        Blade::directive('htmlSelectDateA11y', function ($parameters) {
            return "<?php
                echo \PKP\\template\\PKPTemplateManager::getManager()->smartyHtmlSelectDateA11y($parameters, null);
            ?>";
        });

        // use as @page_info(['iterator' => $results])
        Blade::directive('pageInfo', function ($parameters) {
            return "<?php
                echo \PKP\\template\\PKPTemplateManager::getManager()->smartyPageInfo($parameters, \PKP\\template\\PKPTemplateManager::getManager());
            ?>";
        });

        // use as @page_links(['anchor' => 'results', 'iterator' => $results, 'name' => 'search', ...])
        Blade::directive('pageLinks', function ($parameters) {
            return "<?php
                echo \PKP\\template\\PKPTemplateManager::getManager()->smartyPageLinks($parameters, \PKP\\template\\PKPTemplateManager::getManager());
            ?>";
        });

        // Create a global alias so ViewHelper can be used without full namespace in templates
        AliasLoader::getInstance()->alias('ViewHelper', \PKP\template\ViewHelper::class);    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerViewFinder();
        $this->registerBladeCompiler();
        $this->registerEngineResolver();
        $this->registerFactory();
    }

    /**
     * Register the view environment.
     *
     * @return void
     */
    public function registerFactory()
    {
        $this->app->singleton(
            ViewFactory::class,
            fn (PKPContainer $app) => $this->configureViewFactoryWithBindings($app)
        );

        $this->app->singleton(
            'view',
            fn (PKPContainer $app) => $app->get(ViewFactory::class)
        );
    }

    /**
     * Register the view finder implementation.
     *
     * @return void
     */
    public function registerViewFinder()
    {
        $this->app->singleton(
            'view.finder',
            fn (PKPContainer $app) => new FileViewFinder(
                $app->get('files'), 
                array_values($app->get('config')->get('view.paths'))
            )
        );
    }

    /**
     * Register the Blade compiler implementation.
     *
     * @return void
     */
    public function registerBladeCompiler()
    {
        $this->app->singleton('blade.compiler', function (PKPContainer  $app) {
            $viewConfig = $app->get('config')->get('view'); /** @var array $viewConfig */
            
            return tap(
                new BladeCompiler(
                    $app->get('files'),
                    $viewConfig['compiled'],
                    $viewConfig['relative_hash'] ? $app->basePath() : '',
                    $viewConfig['cache'],
                    $viewConfig['compiled_extension'],
                ),
                function (BladeCompiler $bladeCompiler) {
                    $bladeCompiler->component('dynamic-component', DynamicComponent::class);

                    $this->app->instance(BladeCompiler::class, $bladeCompiler);
                    $this->app->instance(IlluminateBladeCompiler::class, $bladeCompiler);
                    
                    $facadeAccessor = (new class extends \Illuminate\Support\Facades\Blade {
                        public static function getFacadeAccessor() { return parent::getFacadeAccessor(); }
                    })::getFacadeAccessor();
                    $this->app->alias(BladeCompiler::class, $facadeAccessor);
                    $this->app->alias(IlluminateBladeCompiler::class, $facadeAccessor);
                }
            );
        });
    }

    /**
     * Register the Blade engine implementation.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     * @return void
     */
    public function registerBladeEngine($resolver)
    {
        $resolver->register(
            'blade',
            fn () => new CompilerEngine(
                $this->app['blade.compiler'], 
                $this->app['files']
            )
        );
    }

    /**
     * Configure the view factory with required bindings and setting the alias
     *
     * @param  \PKP\core\PKPContainer  $app
     * @return \Illuminate\View\Factory
     */
    protected function configureViewFactoryWithBindings(PKPContainer $app): ViewFactory
    {
        // Next we need to grab the engine resolver instance that will be used by the
        // environment. The resolver will be used by an environment to get each of
        // the various engine implementations such as plain PHP or Blade engine.
        $resolver = $app->get('view.engine.resolver');

        $finder = $app->get('view.finder');

        $factory = $this->createFactory($resolver, $finder, $app->get('events'));

        // We will also set the container instance on this view environment since the
        // view composers may be classes registered in the container, which allows
        // for great testable, flexible composers for the application developer.
        $factory->setContainer($app);

        $factory->share('app', $app);

        $app->instance(\Illuminate\Contracts\View\Factory::class, $factory);
        $app->alias(
            \Illuminate\Contracts\View\Factory::class, 
            (new class extends View {
                public static function getFacadeAccessor() { return parent::getFacadeAccessor(); }
            })::getFacadeAccessor()
        );

        return $factory;
    }
}
