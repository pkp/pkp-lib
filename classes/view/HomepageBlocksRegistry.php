<?php

/**
 * @file classes/view/HomepageBlocksRegistry.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to register and load homepage blocks.
 */

namespace PKP\view;

use APP\core\Application;
use APP\template\TemplateManager;
use Illuminate\Support\Collection;
use PKP\context\Context;
use PKP\plugins\interfaces\HasHomepageBlocks;
use PKP\plugins\Plugin;
use PKP\plugins\PluginRegistry;
use PKP\plugins\ThemePlugin;
use PKP\view\HomepageBlock;

class HomepageBlocksRegistry extends BlocksRegistry
{
    public function load(?Context $context): Collection
    {
        $blocks = $this->get();
        $blocks->each(function(HomepageBlock $block) use ($context) {
            if (isset($block?->loader) && !$block->isLoaded()) {
                call_user_func($block->loader, $context);
                $block->loaded();
            }
        });

        return $blocks;
    }

    /**
     * Only register blocks for the correct context/site level
     */
    public function register(HomepageBlock|Block $block): void
    {
        if (!is_a($block, HomepageBlock::class)) {
            return;
        }

        $context = Application::get()->getRequest()->getContext();
        if (($context && !$block->forContext) || (!$context && !$block->forSite)) {
            return;
        }

        parent::register($block);
    }

    protected function registerAll(): void
    {
        $this->registerDefaultBlocks();

        $plugins = PluginRegistry::getAllPlugins();
        foreach ($plugins as $plugin) {
            /** @var Plugin $plugin */
            /**
             * Theme plugins are handled differently so that
             * only the active theme and parent themes are
             * called.
             */
            if ($plugin instanceOf ThemePlugin) {
                continue;
            }
            if ($plugin instanceOf HasHomepageBlocks) {
                $plugin->registerHomepageBlocks($this);
            }
        }

        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
        $activeTheme = $templateMgr->getActiveTheme($request, $request->getContext());
        if ($activeTheme) {
            $this->registerThemeBlocks($activeTheme);
        }

        $this->hasRegistered = true;
    }

    protected function registerThemeBlocks(ThemePlugin $theme): void
    {
        if ($theme->parent) {
            $this->registerThemeBlocks($theme->parent);
        }
        if ($theme instanceOf HasHomepageBlocks) {
            $theme->registerHomepageBlocks($this);
        }
    }

    protected function registerDefaultBlocks(): void
    {
        $this->register(
            new HomepageBlock(
                component: 'homepage-blocks.announcement',
                title: __('manager.announcements.latest'),
            )
        );
        $this->register(
            new HomepageBlock(
                component: 'homepage-blocks.highlights',
                title: __('common.highlights'),
            )
        );
    }
}