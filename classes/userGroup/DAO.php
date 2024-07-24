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

use APP\core\Application;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\Core;
use PKP\core\EntityDAO;
use PKP\core\PKPApplication;
use PKP\core\traits\EntityWithParent;
use PKP\services\PKPSchemaService;

/**
 * @template T of UserGroup
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    use EntityWithParent;

    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_USER_GROUP;

    /** @copydoc EntityDAO::$table */
    public $table = 'user_groups';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'user_group_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
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
        'masthead' => 'masthead',
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
            ->getCountForPagination();
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
            ->select('ug.' . $this->primaryKeyColumn)
            ->pluck('ug.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of publications matching the configured query
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
                yield $row->user_group_id => $this->fromRow($row);
            }
        });
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
    public function getUserCountByContextId(?int $contextId = Application::SITE_CONTEXT_ID_ALL): Collection
    {
        $currentDateTime = Core::getCurrentDate();
        return DB::table('user_groups', 'ug')
            ->join('user_user_groups AS uug', 'uug.user_group_id', '=', 'ug.user_group_id')
            ->join('users AS u', 'u.user_id', '=', 'uug.user_id')
            ->when($contextId !== Application::SITE_CONTEXT_ID_ALL, fn (Builder $query) => $query->whereRaw('COALESCE(ug.context_id, 0) = ?', [(int) $contextId]))
            ->where('u.disabled', '=', 0)
            ->where(
                fn (Builder $q) =>
                $q->where('uug.date_start', '<=', $currentDateTime)
                    ->orWhereNull('uug.date_start')
            )
            ->where(
                fn (Builder $q) =>
                $q->where('uug.date_end', '>', $currentDateTime)
                    ->orWhereNull('uug.date_end')
            )
            ->groupBy('ug.user_group_id')
            ->select('ug.user_group_id')
            ->selectRaw('COUNT(0) AS count')
            ->pluck('count', 'user_group_id');
    }
}
