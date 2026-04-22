<?php

/**
 * @file classes/testing/scenario/ScenarioProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @interface ScenarioProcessor
 *
 * @brief Contract for processors that realize one aspect of a test-only
 *        scenario spec (e.g. journals, users, sections) via Repo calls.
 *
 * Processors compose to build full scenarios. The controller runs them in
 * a declared dependency order inside a single DB transaction; any throw
 * rolls the whole scenario back.
 */

namespace PKP\testing\scenario;

interface ScenarioProcessor
{
    /**
     * Return true when the processor has work to do for this spec.
     * Lets processors be registered uniformly while skipping cheaply when
     * their spec section is absent.
     */
    public function appliesTo(array $spec): bool;

    /**
     * Execute the processor. Returns an ID map fragment that the controller
     * merges into its response. Keys should be predictable natural keys
     * from the spec (e.g. journal paths, usernames) so tests can reference
     * created entities without parsing opaque IDs.
     */
    public function run(array $spec, ScenarioContext $ctx): array;
}
