<?php
/**
 * @file classes/observers/listeners/UpdateAuthorStageAssignments.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateAuthorStageAssignments
 *
 * @ingroup observers_listeners
 *
 * @brief Update author stage assignments when a submission is submitted
 *   to restrict their ability to edit the metadata, depending on how their
 *   user group is configured.
 */

namespace PKP\observers\listeners;

use APP\facades\Repo;
use Illuminate\Events\Dispatcher;
use PKP\observers\events\SubmissionSubmitted;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;

class UpdateAuthorStageAssignments
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            SubmissionSubmitted::class,
            UpdateAuthorStageAssignments::class
        );
    }

    public function handle(SubmissionSubmitted $event)
    {
        // Replaces StageAssignmentDAO::getBySubmissionAndRoleIds
        $stageAssigments = StageAssignment::withSubmissionIds([$event->submission->getId()])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withStageIds([$event->submission->getData('stageId')])
            ->get();

        $userGroups = Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([$event->context->getId()])
            ->filterByRoleIds([Role::ROLE_ID_AUTHOR])
            ->getMany();

        foreach ($stageAssigments as $stageAssignment) {
            $userGroup = $userGroups->get($stageAssignment->userGroupId);
            if (!$userGroup || $stageAssignment->canChangeMetadata === $userGroup->getPermitMetadataEdit()) {
                continue;
            }
            $stageAssignment->canChangeMetadata = $userGroup->getPermitMetadataEdit();
            $stageAssignment->save();
        }
    }
}
