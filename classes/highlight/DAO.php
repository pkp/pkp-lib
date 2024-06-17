<?php
/**
 * @file classes/highlight/DAO.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @brief Read and write highlights to the database.
 */

namespace PKP\highlight;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;

/**
 * @template T of Highlight
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    public $schema = \PKP\services\PKPSchemaService::SCHEMA_HIGHLIGHT;
    public $table = 'highlights';
    public $settingsTable = 'highlight_settings';
    public $primaryKeyColumn = 'highlight_id';
    public $parentKeyColumn = 'context_id';
    public $primaryTableColumns = [
        'id' => 'highlight_id',
        'contextId' => 'context_id',
        'sequence' => 'sequence',
        'url' => 'url',
    ];

    /**
     * Instantiate a new Highlight
     */
    public function newDataObject(): Highlight
    {
        return app(Highlight::class);
    }

    /**
     * Check if a highlight exists
     */
    public function exists(int $id, ?int $contextId): bool
    {
        return DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->where(DB::raw("COALESCE({$this->parentKeyColumn}, 0)"), (int) $contextId)
            ->exists();
    }

    /**
     * Get a highlight
     */
    public function get(int $id, ?int $contextId): ?Highlight
    {
        $row = DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->where(DB::raw("COALESCE({$this->parentKeyColumn}, 0)"), (int) $contextId)
            ->first();
        return $row ? $this->fromRow($row) : null;
    }

    /**
     * Get the number of highlights matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->get('a.' . $this->primaryKeyColumn)
            ->count();
    }

    /**
     * Get a list of ids matching the configured query
     *
     * @return Collection<int,int>
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->pluck('a.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of highlights matching the configured query
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->highlight_id => $this->fromRow($row);
            }
        });
    }

    public function insert(Highlight $highlight): int
    {
        return parent::_insert($highlight);
    }

    public function update(Highlight $highlight)
    {
        parent::_update($highlight);
    }

    public function delete(Highlight $highlight)
    {
        parent::_delete($highlight);
    }

    /**
     * Get the largest sequence value for a given context
     */
    public function getLastSequence(?int $contextId = null): ?int
    {
        return DB::table($this->table)
            ->where(DB::raw("COALESCE(context_id, 0)"), (int) $contextId)
            ->orderBy('sequence', 'desc')
            ->first('sequence')
            ?->sequence;
    }
}
