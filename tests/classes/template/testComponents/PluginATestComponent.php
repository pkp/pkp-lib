<?php

/**
 * @file tests/classes/template/testComponents/PluginATestComponent.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginATestComponent
 *
 * @brief Generic test fixture: a class-based Blade component used by
 *        TemplateIntegrationTest to verify that ComponentTagCompiler::guessClassName()
 *        resolves an unnamespaced <x-plugin-a-test-component /> tag inside a plugin
 *        template to the plugin's class namespace (pkp/pkp-lib#12684, Case 1).
 *
 */

namespace PKP\tests\classes\template\testComponents;

use Illuminate\View\Component;

class PluginATestComponent extends Component
{
    public function __construct(public string $marker = 'default')
    {
    }

    public function render(): string
    {
        return 'PluginATestComponent:{{ $marker }}';
    }
}
