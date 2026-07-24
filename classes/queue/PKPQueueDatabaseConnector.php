<?php

/**
 * @file classes/queue/PKPQueueDatabaseConnector.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPQueueDatabaseConnector
 *
 * @brief Registers the database queue connector
 */

namespace PKP\queue;

use Illuminate\Queue\Connectors\DatabaseConnector as IlluminateQueueDatabaseConnector;
use PKP\queue\DatabaseQueue;
use PKP\config\Config;

class PKPQueueDatabaseConnector extends IlluminateQueueDatabaseConnector
{
    /**
     * Establish a queue connection.
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        return new DatabaseQueue(
            $this->connections->connection($config['connection'] ?? null),
            $config['table'],
            Config::getVar('queues', 'default_queue', 'queue'),
            $config['retry_after'] ?? 60,
            $config['after_commit'] ?? null
        );
    }
}
