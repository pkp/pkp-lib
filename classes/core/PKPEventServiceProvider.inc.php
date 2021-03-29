<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Events\EventServiceProvider;

class PKPEventServiceProvider extends EventServiceProvider {

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
	 * @return void;
	 * @brief boot the service after registration
	 */
	public function register() {
		parent::register();
	}

	/**
	 * Get the discovered events and listeners for the application.
	 * TODO handle cached events
	 * @return array
	 */
	public function getEvents()
	{
		return $this->listens();

	}

	/**
	 * Get the events and handlers.
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
	public function boot() {

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
}
