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
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\observers\events\SubmissionSubmitted;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\stageAssignment\StageAssignmentDAO;

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
        /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');

        $assignments = $stageAssignmentDao->getBySubmissionAndRoleIds($event->submission->getId(), Role::ROLE_ID_AUTHOR);

        while ($assignment = $assignments->next()) {
            /** @var StageAssignment $assignment */
            $userGroup = Repo::userGroup()->get($assignment->getUserGroupId(), $event->context->getId());
            if (!$userGroup) {
                continue;
            }
            $assignment->setCanChangeMetadata($userGroup->getPermitMetadataEdit());
            $stageAssignmentDao->updateObject($assignment);
        }
    }
}
