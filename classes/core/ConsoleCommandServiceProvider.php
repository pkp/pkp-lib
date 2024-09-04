<?php

/**
 * @file classes/core/ConsoleCommandServiceProvider.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ConsoleCommandServiceProvider
 *
 * @brief Register required component to invoke laravel console commands
 */

namespace PKP\core;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Contracts\Support\DeferrableProvider;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class ConsoleCommandServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register service provider
     */
    public function register()
    {
        $this->app->bind(
            Factory::class,
            fn ($app) => new Factory(
                static::getConsoleOutputStyle()
            )
        );
    }

    /**
     * Get command line output style
     */
    public static function getConsoleOutputStyle(): OutputStyle
    {
        return new OutputStyle(
            ...static::getConsoleIOInstances()
        );
    }

    /**
     * Get console command line input and output components
     */
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
