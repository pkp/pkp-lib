<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12799_MovePublishedSubmissionsToDone.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12799_MovePublishedSubmissionsToDone
 *
 * @brief Move existing published submissions into the Done workflow stage.
 */

namespace PKP\migration\upgrade\v3_6_0;

use APP\decision\Decision;
use APP\facades\Repo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\install\DowngradeNotSupportedException;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\migration\Migration;
use PKP\security\Role;
use PKP\submission\PKPSubmission;

class I12799_MovePublishedSubmissionsToDone extends Migration
{
    /** Cache of the resolved journal manager fallback 'editor', keyed by context id. */
    private array $contextManagerCache = [];

    /** Cache of the resolved site admin fallback editor. */
    private ?int $siteAdminId = null;
    private bool $siteAdminResolved = false;

    /** Cache of editor full names, keyed by user id. */
    private array $editorNameCache = [];

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        // Transfer existing STATUS_PUBLISHED submissions to new WORKFLOW_STAGE_ID_DONE stage.
        DB::table('submissions as s')
            // Get publication to get date_published as well
            ->join('publications as p', 'p.publication_id', '=', 's.current_publication_id')
            // Get assigned editor user ID based on stage assignments and role
            ->leftJoinSub(
                DB::table('stage_assignments as sa')
                    ->join('user_groups as ug', 'ug.user_group_id', '=', 'sa.user_group_id')
                    ->whereIn('ug.role_id', [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER])
                    ->select('sa.submission_id', DB::raw('MIN(sa.stage_assignment_id) as min_assignment_id'))
                    ->groupBy('sa.submission_id'),
                'min_sa',
                'min_sa.submission_id',
                '=',
                's.submission_id'
            )
            ->leftJoin('stage_assignments as sa_first', 'sa_first.stage_assignment_id', '=', 'min_sa.min_assignment_id')
            ->where('s.status', '=', PKPSubmission::STATUS_PUBLISHED)
            ->where('s.stage_id', '<>', WORKFLOW_STAGE_ID_DONE)
            ->select([
                's.submission_id',
                's.current_publication_id',
                's.context_id',
                's.stage_id',
                'p.date_published',
                'sa_first.user_id as assigned_editor_id',
            ])->chunkById(100, function (Collection $submissions) {
                $toUpdate = [];
                foreach ($submissions as $submission) {
                    if ($this->addAndLogDecision($submission)) {
                        $toUpdate[] = $submission->submission_id;
                    }
                }
                if ($toUpdate) {
                    // Move the submissions into the Done stage.
                    // Status is intentionally left untouched:
                    // a runtime MoveToDone leaves the submission as STATUS_PUBLISHED.
                    DB::table('submissions')
                        ->whereIn('submission_id', $toUpdate)
                        ->update(['stage_id' => WORKFLOW_STAGE_ID_DONE]);
                }
            }, 's.submission_id', 'submission_id');
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    /**
     * Record a MOVE_TO_DONE decision (with a matching event-log entry) for a single
     * submission and move it into WORKFLOW_STAGE_ID_DONE.
     *
     * @return bool Whether adding and logging the decision was successful or not
     */
    private function addAndLogDecision(object $submission): bool
    {
        $editorId = $submission->assigned_editor_id
            ?? $this->resolveContextManagerId((int) $submission->context_id)
            ?? $this->resolveSiteAdminId();

        if (!$editorId) {
            error_log("I12799: skipping submission {$submission->submission_id}; no editor, journal manager or site administrator could be resolved.");
            return false;
        }

        $dateDecided = $submission->date_published ?: Core::getCurrentDate();

        // The decision records the stage the submission occupied before Done; this is
        // what a later Return to Workflow decision reads back as the return target.
        DB::table('edit_decisions')->insert([
            'submission_id' => $submission->submission_id,
            'review_round_id' => null,
            'stage_id' => $submission->stage_id,
            'round' => null,
            'editor_id' => $editorId,
            'decision' => Decision::MOVE_TO_DONE,
            'date_decided' => $dateDecided,
        ]);

        // Mirror the event-log entry written by a Decision in Repository::add().
        // Log entry is manually added to the `event_log` table with the editor name added to the settings table.
        $logId = DB::table('event_log')->insertGetId([
            'assoc_type' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assoc_id' => (int) $submission->submission_id,
            'user_id' => $editorId,
            'date_logged' => $dateDecided,
            'event_type' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_EDITOR_DECISION,
            'message' => 'editor.submission.decision.moveToDone.log',
            'is_translated' => 0,
        ], 'log_id');

        DB::table('event_log_settings')->insert([
            'log_id' => $logId,
            'locale' => '',
            'setting_name' => 'editorName',
            'setting_value' => $this->getEditorName($editorId),
        ]);

        return true;
    }

    /**
     * Resolves the context manager user for attributing system-recoded decisions
     */
    private function resolveContextManagerId(int $contextId): ?int
    {
        if (!array_key_exists($contextId, $this->contextManagerCache)) {
            $managerId = DB::table('user_user_groups as uug')
                ->join('user_groups as ug', 'ug.user_group_id', '=', 'uug.user_group_id')
                ->where('ug.context_id', '=', $contextId)
                ->where('ug.role_id', '=', Role::ROLE_ID_MANAGER)
                ->orderBy('uug.user_id')
                ->value('uug.user_id');
            $this->contextManagerCache[$contextId] = $managerId ? (int) $managerId : null;
        }
        if ($this->contextManagerCache[$contextId]) {
            return $this->contextManagerCache[$contextId];
        }

        return null;
    }

    /**
     * Resolves the site admin user for attributing system-recoded decisions
     */
    private function resolveSiteAdminId(): ?int
    {
        if (!$this->siteAdminResolved) {
            $siteAdminId = DB::table('user_user_groups as uug')
                ->join('user_groups as ug', 'ug.user_group_id', '=', 'uug.user_group_id')
                ->where('ug.role_id', '=', Role::ROLE_ID_SITE_ADMIN)
                ->orderBy('uug.user_id')
                ->value('uug.user_id');
            $this->siteAdminId = $siteAdminId ? (int) $siteAdminId : null;
            $this->siteAdminResolved = true;
        }

        return $this->siteAdminId;
    }

    /**
     * Resolve and cache an editor's full name for the event-log entry.
     */
    private function getEditorName(int $editorId): string
    {
        return $this->editorNameCache[$editorId] ??= (Repo::user()->get($editorId)?->getFullName() ?? '');
    }
}
