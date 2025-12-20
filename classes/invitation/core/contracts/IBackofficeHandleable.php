<?php

/**
 * @file classes/invitation/core/contracts/IBackofficeHandleable.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class IBackofficeHandleable
 *
 * @brief Interface for all Invitation API Handlers
 */

namespace PKP\invitation\core\contracts;

interface IBackofficeHandleable
{
    public function finalize(): void;
    public function decline(): void;
}
