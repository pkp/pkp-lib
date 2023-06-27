<?php

namespace PKP\core\database;

use InvalidArgumentException;
use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Database\Connectors\ConnectionFactory;
use PKP\core\database\connections\PKPMySqlConnection;
use PKP\core\database\connections\PKPPostgresConnection;

class PKPDatabaseConnectionFactory extends ConnectionFactory
{
    /**
     * Create a new connection instance.
     *
     * @param  string  $driver
     * @param  \PDO|\Closure  $connection
     * @param  string  $database
     * @param  string  $prefix
     * @param  array  $config
     * @return \Illuminate\Database\Connection
     *
     * @throws \InvalidArgumentException
     */
    protected function createConnection($driver, $connection, $database, $prefix = '', array $config = [])
    {
        if ($resolver = Connection::getResolver($driver)) {
            return $resolver($connection, $database, $prefix, $config);
        }

        return match ($driver) {
            'mysql' => new PKPMySqlConnection($connection, $database, $prefix, $config),
            'pgsql' => new PKPPostgresConnection($connection, $database, $prefix, $config),
            'sqlite' => new SQLiteConnection($connection, $database, $prefix, $config),
            'sqlsrv' => new SqlServerConnection($connection, $database, $prefix, $config),
            default => throw new InvalidArgumentException("Unsupported driver [{$driver}]."),
        };
    }
}