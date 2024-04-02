<?php
/**
 * @file classes/observers/listeners/RestrictAuthorAssignment.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RestrictAuthorAssignment
 *
 * @ingroup observers_listeners
 *
 * @brief Change the authors' stage assignments to restrict their
 *   permission to edit after the submission is complete.
 */

namespace PKP\observers\listeners;

use APP\facades\Repo;
use Illuminate\Events\Dispatcher;
use PKP\observers\events\SubmissionSubmitted;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;

class RestrictAuthorAssignment
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            SubmissionSubmitted::class,
            RestrictAuthorAssignment::class
        );
    }

    public function handle(SubmissionSubmitted $event)
    {
        // Replaces StageAssignmentDAO::getBySubmissionAndRoleIds
        $stageAssignments = StageAssignment::withSubmissionIds([$event->submission->getId()])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->get();

        foreach ($stageAssignments as $stageAssignment) {
            $userGroup = Repo::userGroup()->get($stageAssignment->userGroupId, $event->context->getId());
            if (!$userGroup) {
                continue;
            }
            
            $stageAssignment->canChangeMetadata = $userGroup->getPermitMetadataEdit();
            $stageAssignment->save();
        }
    }
}
