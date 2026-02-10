<?php
/**
 * @file classes/queue/ContextAwareJob.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextAwareJob
 *
 * @brief Abstraction to determine the context id for context aware jobs
 */

namespace PKP\queue;

interface ContextAwareJob
{
    /**
     * Get the context id of this context aware job
     */
    public function getContextId(): int;
}
