<?php

/**
 * @file classes/core/EventServiceProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EventServiceProvider
 *
 * @ingroup core
 *
 * @brief Registers Events Service Provider and boots data on events and their listeners
 */

namespace PKP\core;

use DateInterval;
use Illuminate\Foundation\Events\DiscoverEvents;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as LaravelEventServiceProvider;
use Illuminate\Support\Facades\Cache;
use SplFileInfo;

class EventServiceProvider extends LaravelEventServiceProvider
{
    /** Max lifetime for the event discovery cache */
    protected const MAX_CACHE_LIFETIME = '1 day';

    /**
     * @copydoc \Illuminate\Foundation\Support\Providers\EventServiceProvider::getEvents()
     */
    public function getEvents()
    {
        $expiration = DateInterval::createFromDateString(static::MAX_CACHE_LIFETIME);
        $events = Cache::remember(static::getCacheKey(), $expiration, fn () => $this->discoveredEvents());

        return array_merge_recursive(
            $events,
            $this->listens()
        );
    }

    /**
     * @copydoc \Illuminate\Foundation\Support\Providers\EventServiceProvider::shouldDiscoverEvents()
     */
    public function shouldDiscoverEvents()
    {
        return true;
    }

    /**
     * @copydoc \Illuminate\Foundation\Support\Providers\EventServiceProvider::discoverEvents()
     */
    public function discoverEvents()
    {
        // Adapt classes naming convention
        $discoverEvents = new class () extends DiscoverEvents {
            /**
             * @copydoc \Illuminate\Foundation\Events\DiscoverEvents::classFromFile()
             */
            protected static function classFromFile(SplFileInfo $file, $basePath): string
            {
                return Core::classFromFile($file);
            }
        };

        return collect($this->discoverEventsWithin())
            ->reject(function ($directory) {
                return !is_dir($directory);
            })
            ->reduce(function ($discovered, $directory) use ($discoverEvents) {
                return array_merge_recursive(
                    $discovered,
                    $discoverEvents::within($directory, base_path())
                );
            }, []);
    }

    /**
     * @copydoc \Illuminate\Foundation\Support\Providers\EventServiceProvider::discoverEventsWithin()
     */
    protected function discoverEventsWithin()
    {
        return [
            $this->app->basePath('lib/pkp/classes/observers/listeners'),
            $this->app->basePath('classes/observers/listeners'),
        ];
    }

    /**
     * Clears the event cache
     */
    public static function clearCache(): void
    {
        Cache::forget(static::getCacheKey());
    }

    /**
     * Retrieves a unique and static key to store the event cache
     */
    private static function getCacheKey(): string
    {
        return __METHOD__ . static::MAX_CACHE_LIFETIME;
    }
}
