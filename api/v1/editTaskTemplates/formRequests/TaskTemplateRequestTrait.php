<?php

/**
 * @file api/v1/editTaskTemplates/formRequests/TaskTemplateRequestTrait.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TaskTemplateRequestTrait
 *
 * @brief Common helpers for resolving context, stage IDs and user group rules in task template form requests.
 *
 */

namespace PKP\API\v1\editTaskTemplates\formRequests;

use APP\core\Application;
use Illuminate\Validation\Rule;

trait TaskTemplateRequestTrait
{
    protected function getContextId(): int
    {
        return Application::get()->getRequest()->getContext()->getId();
    }

    protected function getStageIds(): array
    {
        return Application::getApplicationStages();
    }

    protected function userGroupIdsItemRules(int $contextId): array
    {
        return [
            'integer',
            'distinct',
            Rule::exists('user_groups', 'user_group_id')
                ->where(fn ($q) => $q->where('context_id', $contextId)),
        ];
    }
}
