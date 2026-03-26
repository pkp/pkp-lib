<?php

/**
 * @file classes/plugins/PluginSettingsController.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginSettingsController
 *
 * @brief Base controller for plugin settings API endpoints
 *
 * Subclasses must implement the following methods:
 *
 *   public function get(Request $illuminateRequest): JsonResponse
 *   - Return the current plugin settings as JSON.
 *
 *   public function edit(YourFormRequest $illuminateRequest): JsonResponse
 *   - Validate and save plugin settings. The parameter type should be the plugin's
 *     own FormRequest subclass to leverage Laravel's automatic validation.
 *
 * These methods can't be declared abstract here because the edit() parameter type
 * varies per plugin (each uses its own FormRequest subclass).
 */

namespace PKP\plugins;

use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\security\Role;

abstract class PluginSettingsController extends PKPBaseController
{
    public function __construct(
        protected Plugin $plugin
    ) {}

    public function getHandlerPath(): string
    {
        return "plugins/{$this->plugin->getName()}/settings";
    }

    public function getRouteGroupMiddleware(): array
    {
        $middleware = ['has.user'];
        $roles = [Role::ROLE_ID_SITE_ADMIN];

        if (!$this->plugin->isSitePlugin()) {
            $middleware[] = 'has.context';
            $roles[] = Role::ROLE_ID_MANAGER;
        }

        $middleware[] = self::roleAuthorizer($roles);

        return $middleware;
    }

    public function getGroupRoutes(): void
    {
        // Routes expect get() and edit() methods to be implemented by subclasses — see class docblock
        Route::get('', $this->get(...))->name("plugin.{$this->plugin->getName()}.settings.get");
        Route::put('', $this->edit(...))->name("plugin.{$this->plugin->getName()}.settings.edit");
    }
}
