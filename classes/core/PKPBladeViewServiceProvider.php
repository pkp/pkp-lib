<?php

namespace PKP\core;

use Illuminate\View\FileViewFinder;
use Illuminate\View\ViewServiceProvider;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Factory as ViewFactory;
use PKP\core\PKPContainer;
use PKP\core\blade\BladeCompiler;
use PKP\core\blade\DynamicComponent;
use Illuminate\Support\Str;
use PKP\core\PKPString;

class PKPBladeViewServiceProvider extends ViewServiceProvider
{
    /**
     * Boot the service provider
     *
     * @return void
     */
    public function boot()
    {
        // This allows templates to be referenced explicitly, 
        // e.g., @include('VIEW_NAMESPACE::some-template') or @include('VIEW_NAMESPACE::some-template'),
        // or even allow to render view as view('VIEW_NAMESPACE::some-template', [....])
        // which allow to render views from any namespace ambiguity and improving maintainability.
        collect($this->app->get('config')->get('view.paths'))
            ->each(fn ($path, $namespace) => view()->addNamespace($namespace, $path));
        
        // This allows to render components as <x-COMPONENT_NAMESPACE::some-component />
        // which allow to render components from any namespace ambiguity and improving maintainability.
        collect($this->app->get('config')->get('view.components.namespace'))
            ->each(fn ($namespace, $prefix) => \Illuminate\Support\Facades\Blade::componentNamespace($namespace, $prefix));
        
        // Register the @url directive
        \Illuminate\Support\Facades\Blade::directive('url', function ($expression) {
            return "<?php
                \$parameters = $expression ? (array) ($expression) : [];
                echo \PKP\\template\\PKPTemplateManager::getManager()->smartyUrl(\$parameters);
            ?>";
        });

        // FIXME :
        // problem is in blade file we need to use the full namespace like `\Illuminate\Support\Str::sanitizeHtml`
        // Should define a alias ?
        // should move this to a global macro ?
        Str::macro('sanitizeHtml', function ($input, $configKey = 'allowed_html') {
            $sanitized = PKPString::stripUnsafeHtml($input, $configKey);
            $sanitized = str_replace('{{', '<span v-pre>{{</span>', $sanitized);
            $sanitized = str_replace('}}', '<span v-pre>}}</span>', $sanitized);
            return $sanitized;
        });
    }

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
                    $this->app->instance(\Illuminate\View\Compilers\BladeCompiler::class, $bladeCompiler);
                    
                    $facadeAccessor = (new class extends \Illuminate\Support\Facades\Blade {
                        public static function getFacadeAccessor() { return parent::getFacadeAccessor(); }
                    })::getFacadeAccessor();
                    $this->app->alias(BladeCompiler::class, $facadeAccessor);
                    $this->app->alias(\Illuminate\View\Compilers\BladeCompiler::class, $facadeAccessor);
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
            (new class extends \Illuminate\Support\Facades\View {
                public static function getFacadeAccessor() { return parent::getFacadeAccessor(); }
            })::getFacadeAccessor()
        );

        return $factory;
    }
}
