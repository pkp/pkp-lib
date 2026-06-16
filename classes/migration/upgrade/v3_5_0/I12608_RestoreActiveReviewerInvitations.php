<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I12608_RestoreActiveReviewerInvitations.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12608_RestoreActiveReviewerInvitations
 *
 * @brief Restore reviewerAccess invitations that were prematurely marked
 *        ACCEPTED on first link click (see pkp/pkp-lib#12608) back to PENDING,
 *        but only when the underlying review assignment is still active.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\migration\Migration;

class I12608_RestoreActiveReviewerInvitations extends Migration
{
    public function up(): void
    {
        $restored = 0;
        $skipped = 0;

        DB::table('invitations')
            ->where('type', ReviewerAccessInvite::INVITATION_TYPE)
            ->where('status', InvitationStatus::ACCEPTED->value)
            ->orderBy('invitation_id')
            ->chunkById(500, function ($invitations) use (&$restored, &$skipped) {
                // Group this chunk's invitation ids by their reviewAssignmentId payload value
                $invitationIdsByReviewId = [];
                foreach ($invitations as $invitation) {
                    $payload = json_decode($invitation->payload ?? '', true);
                    $reviewAssignmentId = $payload['reviewAssignmentId'] ?? null;

                    if (!$reviewAssignmentId) {
                        $skipped++;
                        continue;
                    }

                    $invitationIdsByReviewId[(int) $reviewAssignmentId][] = $invitation->invitation_id;
                }

                if (empty($invitationIdsByReviewId)) {
                    return;
                }

                // which of those assignments are still active?
                $activeReviewIds = DB::table('review_assignments')
                    ->whereIn('review_id', array_keys($invitationIdsByReviewId))
                    ->whereNull('date_completed')
                    ->pluck('review_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $invitationIdsToRestore = [];
                foreach ($invitationIdsByReviewId as $reviewId => $invitationIds) {
                    if (in_array($reviewId, $activeReviewIds, true)) {
                        $invitationIdsToRestore = array_merge($invitationIdsToRestore, $invitationIds);
                    } else {
                        $skipped += count($invitationIds);
                    }
                }

                if (!empty($invitationIdsToRestore)) {
                    $restored += DB::table('invitations')
                        ->whereIn('invitation_id', $invitationIdsToRestore)
                        ->update(['status' => InvitationStatus::PENDING->value]);
                }
            }, 'invitation_id');

        $this->_installer->log(sprintf(
            'pkp/pkp-lib#12608: Restored %d reviewerAccess invitations to PENDING; '
            . 'skipped %d that referenced completed or missing assignments.',
            $restored,
            $skipped
        ));
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
