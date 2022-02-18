<?php

/**
 * @file controllers/grid/users/userSelect/UserSelectGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserSelectGridHandler
 * @ingroup controllers_grid_users_userSelect
 *
 * @brief Handle user selector grid requests.
 */

use APP\facades\Repo;
use PKP\controllers\grid\feature\CollapsibleGridFeature;
use PKP\controllers\grid\feature\InfiniteScrollingFeature;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;

import('lib.pkp.controllers.grid.users.userSelect.UserSelectGridCellProvider');

class UserSelectGridHandler extends GridHandler
{
    /** @var array (user group ID => user group name) */
    public $_userGroupOptions;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_ASSISTANT],
            ['fetchGrid', 'fetchRows']
        );
    }

    //
    // Implement template methods from PKPHandler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $stageId = (int)$request->getUserVar('stageId');

        $this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getUserGroupsByStage(
            $request->getContext()->getId(),
            $stageId
        );
        $this->_userGroupOptions = [];
        while ($userGroup = $userGroups->next()) {
            // Exclude reviewers.
            if ($userGroup->getRoleId() == Role::ROLE_ID_REVIEWER) {
                continue;
            }
            $this->_userGroupOptions[$userGroup->getId()] = $userGroup->getLocalizedName();
        }

        $this->setTitle('editor.submission.findAndSelectUser');

        // Columns
        $cellProvider = new UserSelectGridCellProvider();
        $this->addColumn(
            new GridColumn(
                'select',
                '',
                null,
                'controllers/grid/users/userSelect/userSelectRadioButton.tpl',
                $cellProvider,
                ['width' => 5]
            )
        );
        $this->addColumn(
            new GridColumn(
                'name',
                'author.users.contributor.name',
                null,
                null,
                $cellProvider,
                ['alignment' => GridColumn::COLUMN_ALIGNMENT_LEFT,
                    'width' => 30
                ]
            )
        );
    }


    //
    // Overridden methods from GridHandler
    //
    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new InfiniteScrollingFeature('infiniteScrolling', $this->getItemsNumber()), new CollapsibleGridFeature()];
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        [$filterUserGroupId, $name] = $this->getFilterValues($filter);
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
        $rangeInfo = $this->getGridRangeInfo($request, $this->getId());
        $users = Repo::user()->getMany(
            $collector = Repo::user()->getCollector()
                ->filterByContextIds([$submission->getContextId()])
                ->filterExcludeSubmissionStage($submission->getId(), $stageId, $filterUserGroupId)
                ->searchPhrase($name)
                ->limit($rangeInfo->getCount())
                ->offset($rangeInfo->getOffset() + max(0, $rangeInfo->getPage() - 1) * $rangeInfo->getCount())
        );
        $totalCount = $users->count($collector->limit(null)->offset(null));
        return new \PKP\core\VirtualArrayIterator(iterator_to_array($users, true), $totalCount, $rangeInfo->getPage(), $rangeInfo->getCount());
    }

    /**
     * @copydoc GridHandler::renderFilter()
     */
    public function renderFilter($request, $filterData = [])
    {
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

        $keys = array_keys($this->_userGroupOptions);
        $allFilterData = array_merge(
            $filterData,
            [
                'userGroupOptions' => $this->_userGroupOptions,
                'selectedUserGroupId' => reset($keys),
                'gridId' => $this->getId(),
                'submissionId' => $submission->getId(),
                'stageId' => $stageId,
            ]
        );
        return parent::renderFilter($request, $allFilterData);
    }

    /**
     * @copydoc GridHandler::getFilterSelectionData()
     */
    public function getFilterSelectionData($request)
    {
        $name = (string) $request->getUserVar('name');
        $filterUserGroupId = (int) $request->getUserVar('filterUserGroupId');
        return [
            'name' => $name,
            'filterUserGroupId' => $filterUserGroupId,
        ];
    }

    /**
     * @copydoc GridHandler::getFilterForm()
     */
    protected function getFilterForm()
    {
        return 'controllers/grid/users/userSelect/searchUserFilter.tpl';
    }

    /**
     * @copydoc GridHandler::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
        return [
            'submissionId' => $submission->getId(),
            'stageId' => $stageId,
        ];
    }

    /**
     * Determine whether a filter form should be collapsible.
     *
     * @return bool
     */
    protected function isFilterFormCollapsible()
    {
        return false;
    }

    /**
     * Define how many items this grid will start loading.
     *
     * @return int
     */
    protected function getItemsNumber()
    {
        return 20;
    }

    /**
     * Process filter values, assigning default ones if
     * none was set.
     *
     * @return array
     */
    protected function getFilterValues($filter)
    {
        if (isset($filter['filterUserGroupId']) && $filter['filterUserGroupId']) {
            $filterUserGroupId = $filter['filterUserGroupId'];
        } else {
            $keys = array_keys($this->_userGroupOptions);
            $filterUserGroupId = reset($keys);
        }
        if (isset($filter['name']) && $filter['name']) {
            $name = $filter['name'];
        } else {
            $name = null;
        }
        return [$filterUserGroupId, $name];
    }
}
