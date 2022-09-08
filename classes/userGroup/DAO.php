<?php

/**
 * @file classes/userGroup/DAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\userGroup\DAO
 *
 * @see \PKP\userGroup\UserGroup
 *
 * @brief Operations for retrieving and modifying UserGroup objects.
 */

namespace PKP\userGroup;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\core\traits\HasParent;
use PKP\services\PKPSchemaService;

class DAO extends EntityDAO
{
    use HasParent;

    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_USER_GROUP;

    /** @copydoc EntityDAO::$table */
    public $table = 'user_groups';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'user_group_settings';

    /** @copydoc EntityDAO::$primarykeyColumn */
    public $primaryKeyColumn = 'user_group_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'user_group_id',
        'contextId' => 'context_id',
        'roleId' => 'role_id',
        'isDefault' => 'is_default',
        'showTitle' => 'show_title',
        'permitSelfRegistration' => 'permit_self_registration',
        'permitMetadataEdit' => 'permit_metadata_edit',
    ];

    /**
     * @copydoc HasParent::getParentColumn()
     */
    public function getParentColumn(): string
    {
        return 'context_id';
    }

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): UserGroup
    {
        return app(UserGroup::class);
    }

    /**
     * Get the total count of rows matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->count();
    }

    /**
     * Get a list of ids matching the configured query
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('ug.' . $this->primaryKeyColumn)
            ->pluck('ug.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of publications matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->user_group_id => $this->fromRow($row);
            }
        });
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): UserGroup
    {
        $userGroup = parent::fromRow($row);

        return $userGroup;
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(UserGroup $userGroup): int
    {
        return parent::_insert($userGroup);
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(UserGroup $userGroup)
    {
        parent::_update($userGroup);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(UserGroup $userGroup)
    {
        parent::_delete($userGroup);
    }

    /**
     * Retrieves a keyed Collection (key = user_group_id, value = count) with the amount of active users for each user group
     */
    public function getUserCountByContextId(?int $contextId = null): Collection
    {
        return DB::table('user_groups', 'ug')
            ->join('user_user_groups AS uug', 'uug.user_group_id', '=', 'ug.user_group_id')
            ->join('users AS u', 'u.user_id', '=', 'uug.user_id')
            ->when($contextId !== null, fn (Builder $query) => $query->where('ug.context_id', '=', $contextId))
            ->where('u.disabled', '=', 0)
            ->groupBy('ug.user_group_id')
            ->select('ug.user_group_id')
            ->selectRaw('COUNT(0) AS count')
            ->pluck('count', 'user_group_id');
    }
}
