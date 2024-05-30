<?php

/**
 * @file classes/institution/DAO.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @ingroup institution
 *
 * @see Institution
 *
 * @brief Operations for retrieving and modifying Institution objects.
 */

namespace PKP\institution;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\core\SoftDeleteTrait;
use PKP\core\traits\EntityWithParent;
use PKP\services\PKPSchemaService;

/**
 * @template T of Institution
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    use EntityWithParent;
    use SoftDeleteTrait;

    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_INSTITUTION;

    /** @copydoc EntityDAO::$table */
    public $table = 'institutions';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'institution_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'institution_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'institution_id',
        'contextId' => 'context_id',
        'ror' => 'ror',
        'deletedAt' => 'deleted_at'
    ];

    /**
     * Get the parent object ID column name
     */
    public function getParentColumn(): string
    {
        return 'context_id';
    }

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Institution
    {
        return App::make(Institution::class);
    }

    /**
     * Get the number of institutions matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->getCountForPagination();
    }

    /**
     * Get a list of institution ids matching the configured query
     *
     * @return Collection<int,int>
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('i.' . $this->primaryKeyColumn)
            ->pluck('i.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of institutions matching the configured query
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->select(['i.*'])
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->institution_id => $this->fromRow($row);
            }
        });
    }

    /**
     * Get a collection of deleted institutions matching the configured query
     */
    public function getSoftDeleted(Collector $query): LazyCollection
    {
        $rows = $query
            ->includeSoftDeletes(true)
            ->getQueryBuilder()
            ->whereNotNull('deleted_at')
            ->select(['i.*'])
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->institution_id => $this->fromRow($row);
            }
        });
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): Institution
    {
        /** @var Institution */
        $institution = parent::fromRow($row);

        $ipRanges = DB::table('institution_ip')
            ->where($this->primaryKeyColumn, '=', $institution->getId())
            ->pluck('ip_string')
            ->toArray();

        $institution->setIPRanges($ipRanges);

        return $institution;
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(Institution $institution): int
    {
        if (isset($institution->_data['ipRanges'])) {
            $ipRanges = $institution->getData('ipRanges');
            unset($institution->_data['ipRanges']);
        }
        $institutionId = parent::_insert($institution);
        if (isset($ipRanges)) {
            $this->insertIPRanges($institutionId, $ipRanges);
        }
        return $institutionId;
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(Institution $institution): void
    {
        if (isset($institution->_data['ipRanges'])) {
            $ipRanges = $institution->getData('ipRanges');
            unset($institution->_data['ipRanges']);
        }
        parent::_update($institution);
        if (isset($ipRanges)) {
            $this->deleteIPRanges($institution->getId());
            $this->insertIPRanges($institution->getId(), $ipRanges);
        }
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(Institution $institution): void
    {
        // If the reference in the table institutional_subscriptions exists, soft delete the institution
        $shouldSoftDelete = DB::table('institutional_subscriptions')
            ->where('institution_id', '=', $institution->getId())
            ->exists();
        if ($shouldSoftDelete) {
            $this->_softDelete($institution);
        } else {
            $this->deleteIPRanges($institution->getId());
            parent::_delete($institution);
        }
    }

    /**
     * Insert institution IP ranges.
     */
    protected function insertIPRanges(int $institutionId, array $ipRanges): void
    {
        if (empty($ipRanges) || empty($institutionId)) {
            return;
        }
        foreach ($ipRanges as $ipRange) {
            $ipStart = null;
            $ipEnd = null;

            $ipRange = trim($ipRange);
            // Parse and check single IP string
            if (strpos($ipRange, Institution::IP_RANGE_RANGE) === false) {
                // Check for wildcards in IP
                if (strpos($ipRange, Institution::IP_RANGE_WILDCARD) === false) {
                    // Get non-CIDR IP
                    if (strpos($ipRange, '/') === false) {
                        $ipStart = sprintf('%u', ip2long($ipRange));

                        // Convert CIDR IP to IP range
                    } else {
                        [$cidrIPString, $cidrBits] = explode('/', $ipRange);

                        if ($cidrBits == 0) {
                            $cidrMask = 0;
                        } else {
                            $cidrMask = (0xffffffff << (32 - $cidrBits));
                        }

                        $ipStart = sprintf('%u', ip2long($cidrIPString) & $cidrMask);

                        if ($cidrBits != 32) {
                            $ipEnd = sprintf('%u', ip2long($cidrIPString) | (~$cidrMask & 0xffffffff));
                        }
                    }

                    // Convert wildcard IP to IP range
                } else {
                    $ipStart = sprintf('%u', ip2long(str_replace(Institution::IP_RANGE_WILDCARD, '0', $ipRange)));
                    $ipEnd = sprintf('%u', ip2long(str_replace(Institution::IP_RANGE_WILDCARD, '255', $ipRange)));
                }

                // Convert wildcard IP range to IP range
            } else {
                [$ipStart, $ipEnd] = explode(Institution::IP_RANGE_RANGE, $ipRange);

                // Replace wildcards in start and end of range
                $ipStart = sprintf('%u', ip2long(str_replace(Institution::IP_RANGE_WILDCARD, '0', trim($ipStart))));
                $ipEnd = sprintf('%u', ip2long(str_replace(Institution::IP_RANGE_WILDCARD, '255', trim($ipEnd))));
            }

            // Insert IP or IP range
            if ($ipStart != null) {
                DB::table('institution_ip')->insert([
                    'institution_id' => $institutionId,
                    'ip_string' => $ipRange,
                    'ip_start' => $ipStart,
                    'ip_end' => $ipEnd
                ]);
            }
        }
    }

    /**
     * Delete institution IP ranges by institution ID.
     */
    private function deleteIPRanges(int $institutionId): void
    {
        DB::table('institution_ip')->where('institution_id', '=', $institutionId)->delete();
    }
}
