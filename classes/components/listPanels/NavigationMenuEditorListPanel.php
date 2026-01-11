<?php

/**
 * @file classes/components/listPanels/NavigationMenuEditorListPanel.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuEditorListPanel
 *
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for editing navigation menus with drag and drop
 */

namespace PKP\components\listPanels;

class NavigationMenuEditorListPanel extends ListPanel
{
    /** @var string URL to the API endpoint for navigation menus */
    public string $apiUrl = '';

    /** @var int|null Initial menu ID to load */
    public ?int $initialMenuId = null;

    /** @var int Maximum nesting depth */
    public int $maxDepth = 3;

    /**
     * @copydoc ListPanel::getConfig()
     */
    public function getConfig(): array
    {
        $config = parent::getConfig();

        $config['apiUrl'] = $this->apiUrl;
        $config['initialMenuId'] = $this->initialMenuId;
        $config['maxDepth'] = $this->maxDepth;

        return $config;
    }
}
