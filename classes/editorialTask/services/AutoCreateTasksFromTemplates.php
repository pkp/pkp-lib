<?php

/**
 * @file classes/editorialTask/services/AutoCreateTasksFromTemplates.php
 *
 * @class AutoCreateTasksFromTemplates
 *
 * @brief Auto-create editorial tasks from templates on stage entry.
 */

namespace PKP\editorialTask\services;

use APP\core\Application;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPApplication;
use PKP\editorialTask\EditorialTask;
use PKP\editorialTask\Template;

class AutoCreateTasksFromTemplates
{
    public function handleStageEntered(Submission $submission, int $stageId): void
    {
        $contextId = (int) $submission->getData('contextId');

        $templates = Template::query()
            ->byContextId($contextId)
            ->filterByStageId($stageId)
            ->filterByInclude(true)
            ->get();

        $request = Application::get()->getRequest();
        $user = $request ? $request->getUser() : null;

        $createdBy = $user ? (int) $user->getId() : (int) ($submission->getData('userId') ?: 0);
        if ($createdBy <= 0) {
            return;
        }

        foreach ($templates as $template) {
            $templateId = (int) $template->id;

            if ($this->taskAlreadyCreatedFromTemplate($submission->getId(), $templateId)) {
                continue;
            }

            $task = $template->promote($submission, false); // no participants
            $task->createdBy = $createdBy;

            $maxSeq = (float) (EditorialTask::query()
                ->where('assoc_type', PKPApplication::ASSOC_TYPE_SUBMISSION)
                ->where('assoc_id', $submission->getId())
                ->max('seq') ?? 0);

            $task->seq = $maxSeq + 1;

            $task->save(); // needs id before settings

            $task->updateSettings(
                [
                    'templateId' => $templateId,
                    'autoCreated' => true,
                ],
                (int) $task->id
            );
        }
    }

    private function taskAlreadyCreatedFromTemplate(int $submissionId, int $templateId): bool
    {
        return DB::table('edit_tasks as t')
            ->join('edit_task_settings as s', 's.edit_task_id', '=', 't.edit_task_id')
            ->where('t.assoc_type', PKPApplication::ASSOC_TYPE_SUBMISSION)
            ->where('t.assoc_id', $submissionId)
            ->where('s.setting_name', 'templateId')
            ->where('s.setting_value', (string) $templateId)
            ->exists();
    }
}
