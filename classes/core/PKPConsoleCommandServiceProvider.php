<?php

namespace PKP\core;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class PKPConsoleCommandServiceProvider extends ServiceProvider
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
}