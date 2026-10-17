<?php

/**
 * @file tests/classes/userGroup/UserUserGroupTest.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserUserGroupTest
 *
 * @ingroup tests_classes_userGroup
 *
 * @brief Tests for the date scopes of the UserUserGroup model.
 */

namespace PKP\tests\classes\userGroup;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\tests\PKPTestCase;
use PKP\userGroup\relationships\UserUserGroup;

#[CoversClass(UserUserGroup::class)]
class UserUserGroupTest extends PKPTestCase
{
    /**
     * Assignments made before 3.5 have no start date (the upgrade adds the
     * column empty) and must be listed as active, like scopeWithActive() does.
     */
    public function testWithActiveAndActiveInFutureIncludesAssignmentsWithoutStartDate(): void
    {
        $userId = DB::table('users')->min('user_id');
        $userGroupId = DB::table('user_groups')->min('user_group_id');
        if (!$userId || !$userGroupId) {
            $this->markTestSkipped('Needs at least one user and one user group.');
        }

        DB::beginTransaction();
        try {
            $insert = fn (?string $start, ?string $end) => DB::table('user_user_groups')->insertGetId([
                'user_id' => $userId,
                'user_group_id' => $userGroupId,
                'date_start' => $start,
                'date_end' => $end,
            ], 'user_user_group_id');

            $noStartDate = $insert(null, null);
            $active = $insert('2000-01-01 00:00:00', null);
            $future = $insert('2999-01-01 00:00:00', null);
            $ended = $insert('2000-01-01 00:00:00', '2001-01-01 00:00:00');
            $endedNoStartDate = $insert(null, '2001-01-01 00:00:00');

            $found = UserUserGroup::query()
                ->whereIn('user_user_group_id', [$noStartDate, $active, $future, $ended, $endedNoStartDate])
                ->withActiveAndActiveInFuture()
                ->pluck('user_user_group_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $this->assertSame([$noStartDate, $active, $future], $found);
        } finally {
            DB::rollBack();
        }
    }
}
