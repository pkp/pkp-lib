<?php

namespace PKP\core\database\traits;

use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Expression;

trait DBConnection
{
    /**
     * Run a select statement against the database and returns a generator.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        $statement = $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            if ($query instanceof Expression) {
                // $grammar = $this instanceof MySqlConnection
                //     ? new MySqlGrammar
                //     : new PostgresGrammar;
                $grammar = getDatabaseQueryGrammar($this); /** @var \Illuminate\Database\Grammar $grammar */
                
                $query = $query->getValue($grammar);
            }

            // First we will create a statement for the query. Then, we will set the fetch
            // mode and prepare the bindings for the query. Once that's done we will be
            // ready to execute the query against the database and return the cursor.

            $statement = $this->prepared($this->getPdoForSelect($useReadPdo)
                              ->prepare($query));

            $this->bindValues(
                $statement, $this->prepareBindings($bindings)
            );

            // Next, we'll execute the query against the database and return the statement
            // so we can return the cursor. The cursor will use a PHP generator to give
            // back one row at a time without using a bunch of memory to render them.
            $statement->execute();

            return $statement;
        });

        while ($record = $statement->fetch()) {
            yield $record;
        }
    }
}