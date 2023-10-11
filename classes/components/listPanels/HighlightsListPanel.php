<?php
/**
 * @file classes/components/listPanels/HighlightsListPanel.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HighlightsListPanel
 *
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for viewing and editing institutions
 */

namespace PKP\components\listPanels;

use PKP\components\forms\highlight\HighlightForm;

class HighlightsListPanel extends ListPanel
{
    /** URL to the API endpoint where items can be retrieved */
    public string $apiUrl = '';

    /** Form for adding or editing a highlight */
    public ?HighlightForm $form = null;

    /** Query parameters to pass if this list executes GET requests  */
    public array $getParams = [];

    /** Max number of items available to display in this list panel  */
    public int $itemsMax = 0;

    public function getConfig(): array
    {
        $config = parent::getConfig();
        $config = array_merge(
            $config,
            [
                'apiUrl' => $this->apiUrl,
                'form' => $this->form->getConfig(),
                'getParams' => $this->getParams,
                'i18nAdd' => __('manager.highlights.add'),
                'i18nConfirmDelete' => __('manager.highlights.confirmDelete'),
                'i18nDelete' => __('manager.highlights.delete'),
                'i18nEdit' => __('manager.highlights.edit'),
                'i18nSaveOrder' => __('grid.action.saveOrdering'),
                'itemsMax' => $this->itemsMax
            ]
        );
        return $config;
    }
}
