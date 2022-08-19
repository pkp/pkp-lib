<?php

/**
 * @file classes/task/PublishSubmissions.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublishSubmissions
 * @ingroup tasks
 *
 * @brief Class to published submissions scheduled for publication.
 */

namespace PKP\task;

use APP\core\Services;
use APP\facades\Repo;

use APP\submission\Submission;
use PKP\core\Core;
use PKP\scheduledTask\ScheduledTask;

class PublishSubmissions extends ScheduledTask
{
    /**
     * @copydoc ScheduledTask::getName()
     */
    public function getName()
    {
        return __('admin.scheduledTask.publishSubmissions');
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    public function executeActions()
    {
        $contextIds = Services::get('context')->getIds([
            'isEnabled' => true,
        ]);
        foreach ($contextIds as $contextId) {
            $submissions = Repo::submission()
                ->getCollector()
                ->filterByContextIds([$contextId])
                ->filterByStatus([Submission::STATUS_SCHEDULED])
                ->getMany();

            foreach ($submissions as $submission) {
                $datePublished = $submission->getCurrentPublication()->getData('datePublished');
                if ($datePublished && strtotime($datePublished) <= strtotime(Core::getCurrentDate())) {
                    Repo::publication()->publish($submission->getCurrentPublication());
                }
            }
        }

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\task\PublishSubmissions', '\PublishSubmissions');
}
