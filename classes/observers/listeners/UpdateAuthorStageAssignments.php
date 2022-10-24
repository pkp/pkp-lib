<?php
/**
 * @file classes/observers/listeners/UpdateAuthorStageAssignments.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateAuthorStageAssignments
 * @ingroup observers_listeners
 *
 * @brief Update author stage assignments when a submission is submitted
 *   to restrict their ability to edit the metadata, depending on how their
 *   user group is configured.
 */

namespace PKP\observers\listeners;

use APP\facades\Repo;
use Illuminate\Events\Dispatcher;
use PKP\db\DAORegistry;
use PKP\observers\events\SubmissionSubmitted;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\stageAssignment\StageAssignmentDAO;

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
        /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $stageAssigments = $stageAssignmentDao->getBySubmissionAndRoleIds(
            $event->submission->getId(),
            [Role::ROLE_ID_AUTHOR],
            $event->submission->getData('stageId')
        );

        $userGroups = Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([$event->context->getId()])
            ->filterByRoleIds([Role::ROLE_ID_AUTHOR])
            ->getMany();

        /** @var StageAssignment $stageAssignment */
        while ($stageAssignment = $stageAssigments->next()) {
            $userGroup = $userGroups->get($stageAssignment->getUserGroupId());
            if (!$userGroup || $stageAssignment->getCanChangeMetadata() === $userGroup->getPermitMetadataEdit()) {
                continue;
            }
            $stageAssignment->setCanChangeMetadata($userGroup->getPermitMetadataEdit());
            $stageAssignmentDao->updateObject($stageAssignment);
        }
    }
}
