<?php

/**
 * @file classes/migration/install/SubmissionsMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsMigration
 * @brief Describe database table structures.
 */

namespace APP\migration\install;

class SubmissionsMigration extends \PKP\migration\install\SubmissionsMigration
{
    protected int $defaultStageId = WORKFLOW_STAGE_ID_PRODUCTION;
}
