<?php

/**
 * @file classes/testing/scenario/Processor/ParticipantProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ParticipantProcessor
 *
 * @brief Attaches users to the scenario's submission as stage assignments.
 *
 * Each participant's role string resolves to the journal's default
 * UserGroup via UserGroupLookup; stage scope is implicit in the group.
 */

namespace PKP\testing\scenario\Processor;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\Core;
use PKP\notification\Notification;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\testing\scenario\ScenarioContext;
use PKP\testing\scenario\ScenarioProcessor;
use PKP\testing\scenario\UserGroupLookup;

class ParticipantProcessor implements ScenarioProcessor
{
    public function appliesTo(array $spec): bool
    {
        return !empty($spec['participants']);
    }

    public function run(array $spec, ScenarioContext $ctx): array
    {
        $submissionId = $ctx->submissionId();
        $contextId = $ctx->submissionContextId();
        $admin = Repo::user()->getByUsername('admin', true);

        $sawEditorRoleAssignment = false;

        foreach ($spec['participants'] as $participantSpec) {
            $user = $ctx->userByUsername($participantSpec['user']);
            $userGroup = UserGroupLookup::userGroupForRole($contextId, $participantSpec['role']);

            $stageAssignment = Repo::stageAssignment()->build(
                $submissionId,
                (int)$userGroup->id,
                $user->getId(),
                $participantSpec['recommendOnly'] ?? null,
                $participantSpec['canChangeMetadata'] ?? null
            );

            // Repo::stageAssignment()->build() is firstOr — when an existing
            // row matches submission+userGroup+user it's returned as-is and
            // any spec flags are silently dropped. The most common collision
            // is the auto-author assignment SubmissionBuilderProcessor seeds
            // for the submitter (default flags), where a later participant
            // entry naming the same user as 'author' must win. Apply the
            // spec's flags explicitly when they were specified and differ.
            $flagUpdates = [];
            if (array_key_exists('recommendOnly', $participantSpec)
                && (bool)$stageAssignment->recommendOnly !== (bool)$participantSpec['recommendOnly']
            ) {
                $flagUpdates['recommendOnly'] = (bool)$participantSpec['recommendOnly'];
            }
            if (array_key_exists('canChangeMetadata', $participantSpec)
                && (bool)$stageAssignment->canChangeMetadata !== (bool)$participantSpec['canChangeMetadata']
            ) {
                $flagUpdates['canChangeMetadata'] = (bool)$participantSpec['canChangeMetadata'];
            }
            if (!empty($flagUpdates)) {
                $stageAssignment->update($flagUpdates);
            }

            // Log the addition. Mirrors StageParticipantGridHandler::
            // saveParticipant — without this, the submission event log is
            // missing 'participant added' entries that production would
            // have written.
            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => Application::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submissionId,
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_ADD_PARTICIPANT,
                'userId' => $admin?->getId(),
                'message' => 'submission.event.participantAdded',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate(),
                'userFullName' => $user->getFullName(),
                'username' => $user->getUsername(),
                'userGroupName' => $userGroup->name,
            ]);
            Repo::eventLog()->add($eventLog);

            if (in_array($userGroup->roleId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], true)) {
                $sawEditorRoleAssignment = true;
            }

            $ctx->recordParticipant(
                $participantSpec['user'],
                $participantSpec['role'],
                (int)$stageAssignment->stageAssignmentId
            );
        }

        // After adding any manager/sub-editor: clear pending
        // EDITOR_ASSIGNMENT_REQUIRED notifications. The
        // SubmissionSubmitted event listener (AssignEditors) auto-creates
        // them per-manager when no auto-assignment fires; the UI flow
        // (StageParticipantGridHandler::saveParticipant) cleans them up
        // once an editor lands. Without this, scenario-seeded
        // submissions would carry stale "editor required" tasks even
        // when an editor is part of the spec.
        if ($sawEditorRoleAssignment) {
            $hasAssignedEditor = StageAssignment::withSubmissionIds([$submissionId])
                ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
                ->exists();
            if ($hasAssignedEditor) {
                Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
                    ->withType(Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED)
                    ->delete();
            }
        }

        return [];
    }
}
