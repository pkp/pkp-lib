<?php

declare(strict_types=1);

/**
 * @file Jobs/Doi/DepositContext.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DepositContext
 * @ingroup jobs
 *
 * @brief Job to deposit all DOIs and associated metadata to the configured registration agency for a given context
 */

namespace PKP\Jobs\Doi;

use APP\core\Application;
use APP\facades\Repo;
use PKP\context\Context;
use PKP\context\ContextDAO;
use PKP\Jobs\BaseJob;
use PKP\job\exceptions\JobException;

class DepositContext extends BaseJob
{
    protected int $contextId;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(int $contextId)
    {
        parent::__construct();

        $this->contextId = $contextId;
    }

    /**
     * Execute the job.
     *
     */
    public function handle()
    {
        /** @var ContextDAO $contextDao */
        $contextDao = Application::getContextDAO();

        /** @var Context $context */
        $context = $contextDao->getById($this->contextId);

        if (!$context) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        // NB: Only run at context level if automatic deposit is enabled. Otherwise, automatic deposit will always run,
        // regardless of configuration status.
        if (!$context->getData(Context::SETTING_DOI_AUTOMATIC_DEPOSIT)) {
            return;
        }

        Repo::doi()->depositAll($context);
    }
}
