<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I13109_PermitPublishedMetadataEdit.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I13109_PermitPublishedMetadataEdit
 *
 * @brief Migrate new workflow stage to allow access to published metadata editing for certain roles.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Support\Facades\DB;
use PKP\migration\HasContextNameHelper;
use PKP\migration\Migration;
use PKP\security\Role;

class I13109_PermitPublishedMetadataEdit extends Migration
{
    use HasContextNameHelper;

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $contextIds = DB::table($this->getContextTableName())->pluck($this->getContextTableKey())->all();

        $groupIds = [];
        foreach ($this->roleUpdateMap() as $roleId => $userGroupNames) {
            if (empty($userGroupNames)) {
                $groupIds = array_merge(
                    $groupIds,
                    DB::table('user_groups')->where('role_id', $roleId)->pluck('user_group_id')->all()
                );
            } else {
                $groupIds = array_merge(
                    $groupIds,
                    DB::table('user_groups as ug')
                        ->where('role_id', $roleId)
                        ->whereExists(
                            fn ($query) =>
                            $query->select(DB::raw(1))
                                ->from('user_group_settings as ugs')
                                ->whereColumn('ug.user_group_id', 'ugs.user_group_id')
                                ->where('setting_name', 'nameLocaleKey')
                                ->whereIn('setting_value', $userGroupNames)
                        )
                        ->pluck('user_group_id')
                        ->all()
                );
            }
        }

        $data = [];
        foreach ($groupIds as $groupId) {
            foreach ($contextIds as $contextId) {
                $data[] = [
                    'context_id' => $contextId,
                    'user_group_id' => $groupId,
                    'stage_id' => 6, // WORKFLOW_STAGE_ID_DONE
                ];
            }
        }

        DB::table('user_group_stage')->whereIn('user_group_id', $groupIds)->insert($data);
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        DB::table('user_group_stage')->where('stage_id', 6)->delete();
    }

    protected function roleUpdateMap(): array
    {
        return [
            Role::ROLE_ID_MANAGER => [],
            Role::ROLE_ID_SUB_EDITOR => [],
            Role::ROLE_ID_ASSISTANT => [
                'default.groups.name.designer',
                'default.groups.name.layoutEditor',
                'default.groups.name.indexer',
                'default.groups.name.proofreader',
            ],
            Role::ROLE_ID_AUTHOR => [],
        ];
    }
}
