<?php

declare(strict_types=1);

/**
 * @file classes/core/PKPQueueDatabaseConnector.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPQueueDatabaseConnector
 * @ingroup core
 *
 * @brief Registers the database queue connector
 */

namespace PKP\core;

use Illuminate\Queue\Connectors\DatabaseConnector as IlluminateQueueDatabaseConnector;
use Illuminate\Queue\DatabaseQueue;
use PKP\config\Config;

class PKPQueueDatabaseConnector extends IlluminateQueueDatabaseConnector
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
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