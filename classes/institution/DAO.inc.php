<?php

/**
 * @file classes/institution/DAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
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
use PKP\plugins\HookRegistry;
use PKP\services\PKPSchemaService;

class DAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_INSTITUTION;

    /** @copydoc EntityDAO::$table */
    public $table = 'institutions';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'institution_settings';

    /** @copydoc EntityDAO::$primarykeyColumn */
    public $primaryKeyColumn = 'institution_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'institution_id',
        'contextId' => 'context_id',
        'ror' => 'ror',
        'deletedAt' => 'deleted_at'
    ];

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Institution
    {
        return App::make(Institution::class);
    }

    /**
     * Check if an institution exists with this ID and context ID
     */
    public function existsByContextId(int $id, int $contextId): bool
    {
        return DB::table($this->table)
            ->where($this->primaryKeyColumn, '=', $id)
            ->where('context_id', '=', $contextId)
            ->exists();
    }

    /**
     * Get the number of institutions matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->select('i.' . $this->primaryKeyColumn)
            ->get()
            ->count();
    }

    /**
     * @copydoc EntityDAO::get()
     */
    public function get(int $id): ?Institution
    {
        return parent::get($id);
    }

    /**
     * Get a list of institution ids matching the configured query
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('i.' . $this->primaryKeyColumn)
            ->pluck('i.' . $this->primaryKeyColumn);
    }

    /**
     * Get a list of institution ids containing the given IP and for the given context ID
     */
    public function getIdsByIP(string $IP, int $contextId): Collection
    {
        $IP = sprintf('%u', ip2long($IP));
        return DB::table($this->table . ' as i')
            ->join('institution_ip as ip', 'ip.institution_id', '=', 'i.institution_id')
            ->where('i.context_id', '=', $contextId)
            ->where(function ($query) use ($IP) {
                $query->whereNotNull('ip.ip_end')
                    ->where('ip.ip_start', '<=', $IP)
                    ->where('ip.ip_end', '>=', $IP);
            })
            ->orWhere(function ($query) use ($IP) {
                $query->whereNull('ip.ip_end')
                    ->where('ip.ip_start', '=', $IP);
            })
            ->select('i.' . $this->primaryKeyColumn)
            ->pluck('i.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of institutions matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->select(['i.*'])
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $this->fromRow($row);
            }
        });
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): Institution
    {
        $institution = parent::fromRow($row);

        $ipRanges = DB::table('institution_ip')
            ->where($this->primaryKeyColumn, '=', (int) $institution->getId())
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
        // If the reference in the table institutional_subscriptitons exists, soft delete the institution
        $shouldSoftDelete = DB::table('institutional_subscriptions')
            ->where('institution_id', '=', $institution->getId())
            ->exists();
        HookRegistry::call('Institution::shouldSoftDelete', [&$shouldSoftDelete]);
        if ($shouldSoftDelete) {
            parent::_softDelete($institution);
        } else {
            parent::_delete($institution);
            $this->deleteIPRanges($institution->getId());
        }
    }

    /**
     * Insert institution IP ranges.
     */
    private function insertIPRanges(int $institutionId, array $ipRanges): void
    {
        if (!empty($ipRanges) && !empty($institutionId)) {
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
    }

    /**
     * Delete institution IP ranges by institution ID.
     */
    private function deleteIPRanges(int $institutionId): void
    {
        DB::table('institution_ip')->where('institution_id', '=', $institutionId)->delete();
    }
}
