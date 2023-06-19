<?php

namespace PKP\core\database\connections;

use Illuminate\Database\MySqlConnection;
use PKP\core\database\traits\DBConnection;

class PKPMySqlConnection extends MySqlConnection
{
    use DBConnection;
}