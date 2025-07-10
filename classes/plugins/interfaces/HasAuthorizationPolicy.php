<?php

/**
 * @file classes/plugins/interfaces/HasAuthorizationPolicy.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasAuthorizationPolicy
 *
 * @brief Provides an interface for plugins which allows the injection of policies in the API request
 */

namespace PKP\plugins\interfaces;

use PKP\core\PKPRequest;

interface HasAuthorizationPolicy
{
    /**
     * Get the authorization policies for the API routes added from a plugin
     */
    public function getPolicies(PKPRequest $request, array &$args, array $roleAssignments): array;
}
