<?php

/**
 * @file classes/core/PKPEventServiceProvider.inc.php
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
use Illuminate\Support\Str;
use PKP\observers\events\MetadataChanged;

use PKP\observers\events\PublishedEvent;
use PKP\observers\events\SubmissionDeleted;
use PKP\observers\events\SubmissionFileDeleted;
use PKP\observers\events\UnpublishedEvent;
use PKP\observers\listeners\MetadataChangedListener;
use PKP\observers\listeners\SubmissionDeletedListener;
use PKP\observers\listeners\SubmissionFileDeletedListener;
use PKP\observers\listeners\SubmissionUpdatedListener;
use SplFileInfo;

class PKPEventServiceProvider extends EventServiceProvider
{
    /**
     * @var array $listen $event => $listeners[]
     * @brief Registering events & listeners, see Illuminate\Events\EventServiceProvider
     */
    protected $listen = [
        SubmissionDeleted::class => [
            SubmissionDeletedListener::class,
        ],
        SubmissionFileDeleted::class => [
            SubmissionFileDeletedListener::class,
        ],
        MetadataChanged::class => [
            MetadataChangedListener::class
        ],
        PublishedEvent::class => [
            SubmissionUpdatedListener::class,
        ],
        UnpublishedEvent::class => [
            SubmissionUpdatedListener::class,
        ]
    ];

    /**
     * @var array
     * @brief to load subscriber classes, currently empty
     */
    protected $subscribe = [];

    /**
     * @return void;
     * @brief boot the service after registration
     */
    public function register()
    {
        parent::register();
    }

    /**
     * Get the discovered events and listeners for the application
     *
     * @return array
     */
    public function getEvents()
    {
        return array_merge_recursive(
            $this->discoveredEvents(),
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
        $discoverEvents = new class() extends DiscoverEvents {
            /**
             * @param string $basePath base path of the application
             *
             * @return string listener's full class name
             */
            protected static function classFromFile(SplFileInfo $file, $basePath): string
            {
                $pathFromBase = trim(Str::replaceFirst($basePath, '', $file->getRealPath()), DIRECTORY_SEPARATOR);
                $libPath = 'lib' . DIRECTORY_SEPARATOR . 'pkp' . DIRECTORY_SEPARATOR;
                $namespace = Str::startsWith($pathFromBase, $libPath) ? 'PKP\\' : 'APP\\';

                $path = $pathFromBase;
                if ($namespace === 'PKP\\') {
                    $path = Str::replaceFirst($libPath, '', $path);
                }
                if (Str::startsWith($path, 'classes')) {
                    $path = Str::replaceFirst('classes' . DIRECTORY_SEPARATOR, '', $path);
                }
                return $namespace . str_replace('/', '\\', Str::replaceLast('.inc.php', '', $path));
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
