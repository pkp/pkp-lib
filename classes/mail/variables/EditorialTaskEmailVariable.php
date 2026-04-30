<?php

/**
 * @file classes/mail/variables/EditorialTaskEmailVariable.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialTaskEmailVariable
 *
 * @ingroup mail_variables
 *
 * @brief Represents variables associated with a task or discussion
 */

namespace PKP\mail\variables;

use APP\core\Application;
use APP\facades\Repo;
use PKP\editorialTask\EditorialTask;
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\mail\Mailable;

class EditorialTaskEmailVariable extends Variable
{
    public const DATE_DUE = 'taskDueDate';
    public const TASK_OWNER_NAME = 'taskOwnerName';

    protected EditorialTask $editorialTask;

    public function __construct(EditorialTask $editorialTask, Mailable $mailable)
    {
        parent::__construct($mailable);

        $this->editorialTask = $editorialTask;
    }

    /**
     * @inheritDoc
     */
    public static function descriptions(): array
    {
        return [
            self::DATE_DUE => __('emailTemplate.variable.task.dateDue'),
            self::TASK_OWNER_NAME => __('emailTemplate.variable.task.taskOwnerName'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function values(string $locale): array
    {
        return [
            self::DATE_DUE => $this->getDueDate(),
            self::TASK_OWNER_NAME => $this->getTaskOwnerName(),
        ];
    }

    /**
     * @return string Get the full name of the user responsible for the task
     */
    protected function getTaskOwnerName(): string
    {
        if ($this->editorialTask->type !== EditorialTaskType::TASK->value) {
            return '';
        }
        $taskOwner = Repo::user()->get($this->editorialTask->participants->where('isResponsible', true)->pluck('userId')->first());
        return htmlspecialchars($taskOwner->getFullName());
    }

    /**
     * @return string The specific date by which the task should be finished
     */
    protected function getDueDate(): string
    {
        if ($this->editorialTask->type !== EditorialTaskType::TASK->value) {
            return '';
        }

        $submissionId = $this->editorialTask->assocId;
        $submission = Repo::submission()->get($submissionId);
        $contextDao = Application::get()->getContextDAO();
        $context = $contextDao->getById($submission->getData('contextId'));
        return $this->editorialTask->dateDue->format($context->getLocalizedDateFormatShort());
    }
}
