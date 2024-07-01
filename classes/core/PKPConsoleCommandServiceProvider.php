<?php

namespace PKP\core;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Contracts\Support\DeferrableProvider;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class PKPConsoleCommandServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register()
    {
        $this->app->bind(Factory::class, function ($app) {
            return new Factory(
                self::getConsoleOutputStyle()
            );
        });
    }

    public static function getConsoleOutputStyle(): OutputStyle
    {
        return new OutputStyle(
            ...self::getConsoleIOInstances()
        );
    }

    public static function getConsoleIOInstances(): array
    {
        return [
            new ArgvInput([]),
            new ConsoleOutput(),
        ];
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            Factory::class,
        ];
    }
}
