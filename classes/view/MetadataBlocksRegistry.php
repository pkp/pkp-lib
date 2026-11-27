<?php

/**
 * @file classes/view/MetadataBlocksRegistry.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to register and load metadata blocks.
 */

namespace PKP\view;

use APP\core\Application;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\Collection;
use PKP\plugins\interfaces\HasMetadataBlocks;
use PKP\plugins\PluginRegistry;
use PKP\plugins\ThemePlugin;
use PKP\view\MetadataBlock;

class MetadataBlocksRegistry extends BlocksRegistry
{
    public function load(Publication $publication, Submission $submission): Collection
    {
        $blocks = $this->get();
        $blocks->each(function(MetadataBlock $block) use ($publication, $submission) {
            if (isset($block?->loader) && !$block->isLoaded()) {
                call_user_func($block->loader, $publication, $submission);
                $block->loaded();
            }
        });

        return $blocks;
    }

    protected function registerAll(): void
    {
        $this->registerDefaultBlocks();
        $this->registerPubIdBlocks();

        $plugins = PluginRegistry::getAllPlugins();
        foreach ($plugins as $plugin) {
            /**
             * Theme plugins are handled differently so that
             * only the active theme and parent themes are
             * called.
             */
            if ($plugin instanceOf ThemePlugin) {
                continue;
            }
            if ($plugin instanceOf HasMetadataBlocks) {
                $plugin->registerMetadataBlocks($this);
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
        if ($theme instanceOf HasMetadataBlocks) {
            $theme->registerMetadataBlocks($this);
        }
    }

    protected function registerDefaultBlocks(): void
    {
        $this->register(
            new MetadataBlock(
                component: 'metadata-blocks.date-published',
                title: __('submissions.published'),
            )
        );
        $this->register(
            new MetadataBlock(
                component: 'metadata-blocks.version',
                title: __('submission.versions'),
            )
        );
        $this->register(
            new MetadataBlock(
                component: 'metadata-blocks.date-submitted',
                title: __('common.dateSubmitted'),
            )
        );
        $this->register(
            new MetadataBlock(
                component: 'metadata-blocks.cover-image',
                title: __('category.coverImage'),
            )
        );
        $this->register(
            new MetadataBlock(
                component: 'metadata-blocks.doi',
                title: __('doi.readerDisplayName'),
            )
        );
        $this->register(
            new MetadataBlock(
                component: 'metadata-blocks.keywords',
                title: __('common.keywords'),
            )
        );
        $this->register(
            new MetadataBlock(
                component: 'metadata-blocks.categories',
                title: __('category.category'),
            )
        );
        $this->register(
            new MetadataBlock(
                component: 'metadata-blocks.data-availability',
                title: __('submission.dataAvailability'),
            )
        );
        $this->register(
            new MetadataBlock(
                component: 'metadata-blocks.funding-statement',
                title: __('submission.fundingStatement'),
            )
        );
        $this->register(
            new MetadataBlock(
                component: 'metadata-blocks.license',
                title: __('submission.license'),
            )
        );
        $this->register(
            new MetadataBlock(
                component: 'metadata-blocks.peer-review',
                title: __('submission.peerReview'),
            )
        );
    }

    protected function registerPubIdBlocks(): void
    {
        $plugins = PluginRegistry::loadCategory('pubIds', true);

        foreach ($plugins as $plugin) {
            $this->register(
                new MetadataBlock(
                    id: $plugin->getPubIdType(),
                    component: 'metadata-blocks.pubid',
                    title: $plugin->getPubIdDisplayType(),
                )
            );
        }
    }
}