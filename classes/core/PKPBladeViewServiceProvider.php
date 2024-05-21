<?php

namespace PKP\core;

use PKP\core\PKPContainer;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\FileViewFinder;
use Illuminate\View\DynamicComponent;
use Illuminate\View\ViewServiceProvider;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Compilers\BladeCompiler;

class PKPBladeViewServiceProvider extends ViewServiceProvider
{
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
                [
                    $app->basePath('/templates'),
                    $app->basePath('/lib/pkp/templates'),
                ]
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
            return tap(new BladeCompiler(
                $app->get('files'),
                BASE_SYS_DIR . '/compiled',
                $app->get('config')->get('view.relative_hash', false) ? $app->basePath() : '',
                $app->get('config')->get('view.cache', true),
                $app->get('config')->get('view.compiled_extension', 'php'),
            ), function (BladeCompiler $bladeCompiler) {
                $bladeCompiler->component('dynamic-component', DynamicComponent::class);
                $this->app->instance(BladeCompiler::class, $bladeCompiler);
                $this->app->alias(
                    BladeCompiler::class, 
                    (new class extends \Illuminate\Support\Facades\Blade {
                        public static function getFacadeAccessor() { return parent::getFacadeAccessor(); }
                    })::getFacadeAccessor()
                );
            });
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
