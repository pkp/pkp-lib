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

use PKP\db\DAORegistry;
use PKP\i18n\PKPLocale;
use PKP\stageAssignment\StageAssignment;

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
    protected function values(): array
    {
        return
        [
            self::DECISION_MAKING_EDITORS => $this->getEditors(),
        ];
    }

    /**
     * Full names of editors associated with an assignment
     */
    protected function getEditors() : array
    {
        $editorsStrLocalized = [];
        $supportedLocales = PKPLocale::getSupportedLocales();
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $userDao = DAORegistry::getDAO('UserDAO');
        $editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($this->stageAssignment->getSubmissionId(), $this->stageAssignment->getStageId());
        foreach ($supportedLocales as $localeKey => $localeValue) {
            $editorsStr = '';
            $i = 0;
            foreach ($editorsStageAssignments as $editorsStageAssignment) {
                if (!$editorsStageAssignment->getRecommendOnly()) {
                    $editorFullName = $userDao->getUserFullName($editorsStageAssignment->getUserId());
                    $editorsStr .= ($i == 0) ? $editorFullName : ', ' . $editorFullName;
                    $i++;
                }
            }
            $editorsStrLocalized[$localeKey] = $editorsStr;
        }

        return $editorsStrLocalized;
    }
}
