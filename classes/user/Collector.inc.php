<?php
/**
 * @file classes/user/Collector.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of users
 */

namespace PKP\user;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

use PKP\core\interfaces\CollectorInterface;
use PKP\identity\Identity;
use PKP\plugins\HookRegistry;

class Collector implements CollectorInterface
{
    /** @var DAO */
    public $dao;

    /** @var array|null */
    public $userGroupIds = null;

    /** @var array|null */
    public $contextIds = null;

    /** @var string */
    public $searchPhrase = '';

    /** @var int */
    public $count = 30;

    /** @var int */
    public $offset = 0;

    /** @var array list of columns to select with query */
    protected $columns = [];

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /**
     * Limit results to users in these user groups
     */
    public function filterByUserGroupIds(array $userGroupIds): self
    {
        $this->userGroupIds = $userGroupIds;
        return $this;
    }

    /**
     * Limit results to users with user groups in these context IDs
     */
    public function filterByContextIds(array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Limit results to users matching this search query
     */
    public function searchPhrase(string $phrase): self
    {
        $this->searchPhrase = $phrase;
        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(int $count): self
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $this->columns[] = 'u.*';
        $q = DB::table('users AS u')
            ->when($this->userGroupIds, function ($query, $userGroupIds) {
                return $query->join('user_user_groups AS uug', function ($join) use ($userGroupIds) {
                    return $join->on('uug.user_id', '=', 'u.user_id')
                        ->whereIn('uug.user_group_id', $userGroupIds);
                });
            })
            ->when($this->contextIds, function ($query, $contextIds) {
                return $query->whereIn('u.user_id', function ($query) use ($contextIds) {
                    return $query->select('uugc.user_id')
                        ->from('user_user_groups AS uugc')
                        ->join('user_groups AS ugc', 'ugc.user_group_id', '=', 'uugc.user_group_id')
                        ->whereIn('ugc.context_id', $contextIds);
                });
            })
            ->when($this->searchPhrase !== null, function ($query) {
                // FIXME: Work better with multiword phrases !!!
                return $query->whereIn('u.user_id', function ($query) {
                    return $query->select('us.user_id')
                        ->from('user_settings AS us')
                        ->where('us.setting_value', 'LIKE', '%' . addcslashes(String::strtolower($this->searchPhrase), '%_') . '%')
                        ->whereIn('LOWER(us.setting_name)', [Identity::IDENTITY_SETTING_GIVENNAME, Identity::IDENTITY_SETTING_FAMILYNAME]);
                });
            });

        // Limit and offset results for pagination
        if (!is_null($this->count)) {
            $q->limit($this->count);
        }
        if (!is_null($this->offset)) {
            $q->offset($this->offset);
        }

        // Add app-specific query statements
        HookRegistry::call('User::Collector::getQueryBuilder', [&$q, $this]);

        $q->select($this->columns);
        error_log($q->toSql());
        return $q;
    }
}
