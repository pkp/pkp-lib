<?php
/**
 * @file classes/userGroup/Collector.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\userGroup\Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of userGroups
 */

namespace PKP\userGroup;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\HookRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class Collector implements CollectorInterface
{
    public const ORDERBY_ROLE_ID = 'roleId';
    public const ORDERBY_ID = 'id';

    /** @var string|null The default orderBy value for userGroups collector */
    public $orderBy = null;

    /** @var DAO */
    public $dao;

    /** @var array|null */
    public $userGroupIds = null;

    /** @var array|null */
    public $contextIds = null; // getByContextId,

    /** @var array|null */
    public $roleIds = null;

    /** @var array|null */
    public $stageIds = null; // getUserGroupsByStage

    /** @var bool|null */
    public ?bool $isDefault = null;

    /** @var bool|null */
    public ?bool $isRecommendOnly = null; // getRecommendOnlyGroupIds

    /** @var bool|null */
    public ?bool $permitSelfRegistration = null; // getRecommendOnlyGroupIds

    /** @var bool|null */
    public ?bool $permitMetadataEdit = null;

     /** @var bool|null */
    public ?bool $showTitle = null; // getPermitMetadataEditGroupIds

    /** @var array|null */
    public $userIds = null;

    public ?int $count = null;

    public ?int $offset = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    public function getIds(): Collection
    {
        return $this->dao->getIds($this);
    }

    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    /**
     * Filter by multiple ids
     */
    public function filterByUserGroupIds(?array $ids): self
    {
        $this->userGroupIds = $ids;
        return $this;
    }

    /**
     * Filter by contexts
     */
    public function filterByContextIds(?array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter by roles
     */
    public function filterByRoleIds(?array $roleIds): self
    {
        $this->roleIds = $roleIds;
        return $this;
    }

    /**
     * Filter by contexts
     */
    public function filterByStageIds(?array $stageIds): self
    {
        $this->stageIds = $stageIds;
        return $this;
    }

    /**
     * Filter by is default
     */
    public function filterByIsDefault(?bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    /**
     * Filter by show title
     */
    public function filterByShowTitle(?bool $showTitle): self
    {
        $this->showTitle = $showTitle;
        return $this;
    }

    /**
     * Filter by permit self registration
     */
    public function filterByPermitSelfRegistration(?bool $permitSelfRegistration): self
    {
        $this->permitSelfRegistration = $permitSelfRegistration;
        return $this;
    }

    /**
     * Filter by permit metadata edit
     */
    public function filterByPermitMetadataEdit(?bool $permitMetadataEdit): self
    {
        $this->permitMetadataEdit = $permitMetadataEdit;
        return $this;
    }

    /**
     * Filter by permit metadata edit
     */
    public function filterByIsRecommendOnly(): self
    {
        $this->isRecommendOnly = true;
        return $this;
    }

    /**
     * Filter by user ids
     */
    public function filterByUserIds(?array $userIds): self
    {
        $this->userIds = $userIds;
        return $this;
    }

    /**
     * Include orderBy columns to the collector query
     */
    public function orderBy(?string $orderBy): self
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(?int $count): self
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(?int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $q = DB::table('user_groups as a')
            ->select('a.*');

        if (isset($this->userGroupIds)) {
            $q->whereIn('a.user_group_id', $this->userGroupIds);
        }

        if (isset($this->userIds)) {
            $q->join('user_user_groups as uug', 'a.user_group_id', '=', 'uug.user_group_id');
            $q->whereIn('uug.user_id', $this->userIds);
        }

        if (isset($this->stageIds)) {
            $q->join('user_group_stage as ugs', function($join) {
                $join->on('a.user_group_id', '=', 'ugs.user_group_id');
                $join->on('a.context_id','=', 'ugs.context_id');
            })->whereIn('ugs.stage_id', $this->stageIds);
        }

        if (isset($this->contextIds)) {
            $q->whereIn('a.context_id', $this->contextIds);
        }

        if (isset($this->roleIds)) {
            $q->whereIn('a.role_id', $this->roleIds);
        }

        $q->when($this->isRecommendOnly !== null, function (Builder $q) {
            $q->whereIn('a.user_group_id', function (Builder $q) {
                $q->select('user_group_id')
                    ->from($this->dao->settingsTable)
                    ->where('setting_name', '=', 'recommendOnly')
                    ->where('setting_value', $this->isRecommendOnly);
            });
        });

        $q->when($this->isDefault !== null, function (Builder $q) {
            $q->where('a.is_default', $this->isDefault);
        });

        $q->when($this->permitSelfRegistration !== null, function (Builder $q) {
            $q->where('a.permit_self_registration', $this->permitSelfRegistration);
        });

        $q->when($this->permitMetadataEdit !== null, function (Builder $q) {
            $q->where('a.permit_metadata_edit', $this->permitMetadataEdit);
        });

        $q->when($this->showTitle !== null, function (Builder $q) {
            $q->where('a.show_title', $this->showTitle);
        });

        if (isset($this->count)) {
            $q->limit($this->count);
        }

        if (isset($this->offset)) {
            $q->offset($this->offset);
        }

        switch ($this->orderBy) {
             case self::ORDERBY_ROLE_ID:
                $q->orderBy('a.role_id', 'asc');
                break;
            case self::ORDERBY_ID:
                $q->orderBy('a.user_group_id', 'asc');
                break;
        }

        // Add app-specific query statements
        HookRegistry::call('UserGroup::Collector', [&$q, $this]);

        return $q;
    }
}
