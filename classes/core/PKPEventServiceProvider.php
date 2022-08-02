<?php

/**
 * @file classes/core/PKPEventServiceProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEventServiceProvider
 * @ingroup core
 *
 * @brief Registers Events Service Provider and boots data on events and their listeners
 */

namespace PKP\core;

use Illuminate\Events\EventServiceProvider;

use Illuminate\Foundation\Events\DiscoverEvents;
use Illuminate\Support\Facades\Event;

use PKP\cache\CacheManager;
use PKP\cache\FileCache;

use SplFileInfo;

class PKPEventServiceProvider extends EventServiceProvider
{
    /**
     * @var array $listen $event => $listeners[]
     * @brief Registering events & listeners, see Illuminate\Events\EventServiceProvider
     */
    protected $listen = [];

    /**
     * @var array
     * @brief to load subscriber classes, currently empty
     */
    protected $subscribe = [];

    /**
     * Get the discovered events and listeners for the application
     *
     * @return array
     */
    public function getEvents()
    {
        $cacheManager = CacheManager::getManager();
        $cache = $cacheManager->getCache('event', PKPApplication::CONTEXT_SITE, function (FileCache $cache) {
            $cache->setEntireCache($this->discoveredEvents());
        });


        return array_merge_recursive(
            $cache->getContents(),
            $this->listens()
        );
    }

    /**
     * Get the events and handlers
     *
     * @return array
     */
    public function listens()
    {
        return $this->listen;
    }

    /**
     * @brief Boot events
     */
    public function boot()
    {
        $events = $this->getEvents();

        foreach ($events as $event => $listeners) {
            foreach (array_unique($listeners) as $listener) {
                Event::listen($event, $listener);
            }
        }

        foreach ($this->subscribe as $subscriber) {
            Event::subscribe($subscriber);
        }
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return true;
    }

    /**
     * Get the discovered events for the application.
     *
     * @return array
     */
    protected function discoveredEvents()
    {
        return $this->shouldDiscoverEvents()
            ? $this->discoverEvents()
            : [];
    }

    /**
     * Discover the events and listeners for the application.
     *
     * @return array
     */
    public function discoverEvents()
    {

        // Adapt classes naming convention
        $discoverEvents = new class () extends DiscoverEvents {
            /**
             * @param string $basePath base path of the application
             *
             * @return string listener's full class name
             */
            protected static function classFromFile(SplFileInfo $file, $basePath): string
            {
                return Core::classFromFile($file);
            }
        };

        return collect($this->discoverEventsWithin())
            ->reject(function ($directory) {
                return ! is_dir($directory);
            })
            ->reduce(function ($discovered, $directory) use ($discoverEvents) {
                return array_merge_recursive(
                    $discovered,
                    $discoverEvents::within($directory, base_path())
                );
            }, []);
    }

    /**
     * Get the listener directories that should be used to discover events.
     *
     * @return array
     */
    protected function discoverEventsWithin()
    {
        return [
            $this->app->basePath('lib/pkp/classes/observers/listeners'),
            $this->app->basePath('classes/observers/listeners'),
        ];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPEventServiceProvider', '\PKPEventServiceProvider');
}
