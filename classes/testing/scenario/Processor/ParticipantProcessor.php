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

use APP\facades\Repo;
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

            $ctx->recordParticipant(
                $participantSpec['user'],
                $participantSpec['role'],
                (int)$stageAssignment->stageAssignmentId
            );
        }

        return [];
    }
}
