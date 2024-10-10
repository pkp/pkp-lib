<?php
/**
 * @file classes/ror/DAO.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\ror\DAO
 *
 * @ingroup ror
 *
 * @see Ror
 *
 * @brief Read and write ror cache to the database.
 */

namespace PKP\ror;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\core\traits\EntityWithParent;
use PKP\services\PKPSchemaService;

/**
 * @template T of Ror
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    use EntityWithParent;

    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_ROR;

    /** @copydoc EntityDAO::$table */
    public $table = 'rors';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'ror_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'ror_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'ror_id',
        'ror' => 'ror',
        'displayLocale' => 'display_locale',
        'isActive' => 'is_active'
    ];

    /**
     * Get the parent object ID column name
     *
     * @return string
     */
    public function getParentColumn(): string
    {
        return 'ror_id';
    }

    /**
     * Instantiate a new DataObject
     *
     * @return Ror
     */
    public function newDataObject(): Ror
    {
        return App::make(Ror::class);
    }

    /**
     * Get the number of RORs matching the configured query
     *
     * @param Collector $query
     *
     * @return int
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->getCountForPagination();
    }

    /**
     * Get a list of ids matching the configured query
     *
     * @param Collector $query
     *
     * @return Collection<int,int>
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('r.' . $this->primaryKeyColumn)
            ->pluck('r.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of rors matching the configured query
     *
     * @param Collector $query
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
                yield $row->ror_id => $this->fromRow($row);
            }
        });
    }

    /** @copydoc EntityDAO::fromRow() */
    public function fromRow(object $row): Ror
    {
        /** @var Ror $ror */
        $ror = parent::fromRow($row);

        return $ror;
    }

    /** @copydoc EntityDAO::insert() */
    public function insert(Ror $ror): int
    {
        return parent::_insert($ror);
    }

    /** @copydoc EntityDAO::update() */
    public function update(Ror $ror): void
    {
        if (empty($ror->getId())) {
            $ror->setId($this->getIdByRor($ror->getData('ror')));
        }

        parent::_update($ror);
    }

    /** @copydoc EntityDAO::delete() */
    public function delete(Ror $ror): void
    {
        parent::_delete($ror);
    }

    /**
     * Get ror_id for given ror.
     *
     * @param string $ror
     *
     * @return int
     */
    public function getIdByRor(string $ror): int
    {
        $row = DB::table($this->table)
            ->where('ror', '=', $ror)
            ->first($this->primaryKeyColumn);

        if (!empty($row->{$this->primaryKeyColumn})) {
            return $row->{$this->primaryKeyColumn};
        }

        return 0;
    }

    /**
     * Check if ror exists with given ror
     *
     * @param string $ror
     *
     * @return bool
     */
    public function existsByRor(string $ror): bool
    {
        return DB::table($this->table)
            ->where('ror', '=', $ror)
            ->exists();
    }

    /**
     * Insert on duplicate update.
     *
     * @param Ror $ror
     *
     * @return void
     */
    public function updateOrInsert(Ror $ror): void
    {
        if ($this->existsByRor($ror->getData('ror'))) {
            $this->update($ror);
        } else {
            $this->insert($ror);
        }
    }
}
