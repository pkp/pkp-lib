<?php

/**
 * @file classes/editorialTask/services/AutoCreateTasksFromTemplates.php
 *
 * @class AutoCreateTasksFromTemplates
 *
 * @brief Auto-create editorial tasks from templates on stage entry.
 */

namespace PKP\editorialTask\services;

use APP\submission\Submission;
use APP\facades\Repo;

class AutoCreateTasksFromTemplates
{
    public function handleStageEntered(Submission $submission, int $stageId): void
    {
        Repo::editorialTask()->autoCreateFromTemplates($submission, $stageId);
    }
}
