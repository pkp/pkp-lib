<?php

/**
 * @file classes/mail/variables/StageAssignmentEmailVariable.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageAssignmentEmailVariable
 * @ingroup mail_variables
 *
 * @brief Represents email template variables that are associated with stage assignments
 */

namespace PKP\mail\variables;

use APP\facades\Repo;
use PKP\db\DAORegistry;
use PKP\stageAssignment\StageAssignment;
use PKP\stageAssignment\StageAssignmentDAO;

class StageAssignmentEmailVariable extends Variable
{
    const DECISION_MAKING_EDITORS = 'editors';

    protected StageAssignment $stageAssignment;

    public function __construct(StageAssignment $stageAssignment)
    {
        $this->stageAssignment = $stageAssignment;
    }

    /**
     * @copydoc Variable::description()
     */
    protected static function description(): array
    {
        return
        [
            self::DECISION_MAKING_EDITORS => __('emailTemplate.variable.stageAssignment.editors'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    public function values(string $locale): array
    {
        return
        [
            self::DECISION_MAKING_EDITORS => $this->getEditors($locale),
        ];
    }

    /**
     * Full names of editors associated with an assignment
     */
    protected function getEditors(string $locale): string
    {
        /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($this->stageAssignment->getSubmissionId(), $this->stageAssignment->getStageId());

        $editorNames = [];
        foreach ($editorsStageAssignments as $editorsStageAssignment) {
            if (!$editorsStageAssignment->getRecommendOnly()) {
                $user = Repo::user()->get($editorsStageAssignment->getUserId());
                $editorNames[] = $user->getFullName(true, false, $locale);
            }
        }

        return join(__('common.commaListSeparator'), $editorNames);
    }
}
