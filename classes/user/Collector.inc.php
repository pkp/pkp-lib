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
use PKP\core\PKPString;
use PKP\identity\Identity;
use PKP\plugins\HookRegistry;

class Collector implements CollectorInterface
{
    /** @var DAO */
    public $dao;

    /** @var array|null */
    public $userGroupIds = null;

    /** @var array|null */
    public $roleIds = null;

    /** @var array|null */
    public $contextIds = null;

    /** @var ?string */
    public $searchPhrase = null;

    /** @var array|null */
    public $excludeSubmissionStage = null;

    /** @var array|null */
    public $submissionAssignment = null;

    /** @var int|null */
    public $count = null;

    /** @var int|null */
    public $offset = null;

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
     * Limit results to users enrolled in these roles
     */
    public function filterByRoleIds(array $roleIds): self
    {
        $this->roleIds = $roleIds;
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
     * Retrieve a set of users not assigned to a given submission stage as a user group.
     * (Replaces UserStageAssignmentDAO::getUsersNotAssignedToStageInUserGroup)
     */
    public function filterExcludeSubmissionStage(int $submissionId, int $stageId, int $userGroupId): self
    {
        $this->excludeSubmissionStage = [
            'submission_id' => $submissionId,
            'stage_id' => $stageId,
            'user_group_id' => $userGroupId,
        ];
        return $this;
    }

    /**
     * Retrieve StageAssignments by submission and stage IDs.
     * (Replaces UserStageAssignmentDAO::getUsersBySubmissionAndStageId)
     */
    public function filterSubmissionAssignment(int $submissionId, ?int $stageId, ?int $userGroupId): self
    {
        $this->submissionAssignment = [
            'submission_id' => $submissionId,
            'stage_id' => $stageId,
            'user_group_id' => $userGroupId,
        ];
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
            ->when($this->userGroupIds !== null || $this->roleIds !== null || $this->contextIds !== null, function ($query) {
                return $query->whereIn('u.user_id', function ($query) {
                    return $query->select('uug.user_id')
                        ->from('user_user_groups AS uug')
                        ->join('user_groups AS ug', 'uug.user_group_id', '=', 'ug.user_group_id')
                        ->when($this->userGroupIds !== null, function ($query) {
                            return $query->whereIn('uug.user_group_id', $this->userGroupIds);
                        })
                        ->when($this->roleIds !== null, function ($query) {
                            return $query->whereIn('ug.role_id', $this->roleIds);
                        })
                        ->when($this->contextIds !== null, function ($query) {
                            return $query->whereIn('ug.context_id', $this->contextIds);
                        });
                });
            })
            ->when($this->excludeSubmissionStage !== null, function ($query) {
                $query->join('user_user_groups AS uug_exclude', 'u.user_id', '=', 'uug_exclude.user_id')
                    ->join('user_group_stage AS ugs_exclude', function ($join) {
                        return $join->on('uug_exclude.user_group_id', '=', 'ugs_exclude.user_group_id')
                            ->where('ugs_exclude.stage_id', '=', $this->excludeSubmissionStage['stage_id']);
                    })
                    ->leftJoin('stage_assignments AS sa_exclude', function ($join) {
                        return $join->on('sa_exclude.user_id', '=', 'uug_exclude.user_id')
                            ->on('sa_exclude.user_group_id', '=', 'uug_exclude.user_group_id')
                            ->where('sa_exclude.submission_id', '=', $this->excludeSubmissionStage['submission_id']);
                    })
                    ->where('uug_exclude', '=', $this->excludeSubmissionStage['user_group_id'])
                    ->whereNull('sa_exclude.user_group_id');
            })
            ->when($this->submissionAssignment !== null, function ($query) {
                return $query->whereIn('u.user_id', function ($query) {
                    return $query->select('sa.user_id')
                        ->from('stage_assignments AS sa')
                        ->join('user_group_stage AS ugs', 'sa.user_group_id', '=', 'ugs.user_group_id')
                        ->when(isset($this->submissionAssignment['submission_id']), function ($query) {
                            return $query->where('sa.submission_id', '=', $this->submissionAssignment['submission_id']);
                        })
                        ->when(isset($this->submissionAssignment['stage_id']), function ($query) {
                            return $query->where('ugs.stage_id', '=', $this->submissionAssignment['stage_id']);
                        })
                        ->when(isset($this->submissionAssignment['user_group_id']), function ($query) {
                            return $query->where('sa.user_group_id', '=', $this->submissionAssignment['user_group_id']);
                        });
                });
            })
            ->when($this->searchPhrase !== null, function ($query) {
                // FIXME: Work better with multiword phrases !!!
                return $query->whereIn('u.user_id', function ($query) {
                    return $query->select('us.user_id')
                        ->from('user_settings AS us')
                        ->where('us.setting_value', 'LIKE', '%' . addcslashes(PKPString::strtolower($this->searchPhrase), '%_') . '%')
                        ->whereIn(DB::raw('LOWER(us.setting_name)'), [Identity::IDENTITY_SETTING_GIVENNAME, Identity::IDENTITY_SETTING_FAMILYNAME]);
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
        return $q;
    }
}
