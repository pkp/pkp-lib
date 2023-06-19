<?php

namespace PKP\core\database\connections;

use PKP\core\database\traits\DBConnection;
use Illuminate\Database\PostgresConnection;

class PKPPostgresConnection extends PostgresConnection
{
    use DBConnection;
}