<?php
/**
 * @file classes/components/listPanels/PKPInstitutionsListPanel.inc.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInstitutionsListPanel
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for viewing and editing institutions
 */

namespace PKP\components\listPanels;

use PKP\components\forms\institution\PKPInstitutionForm;

class PKPInstitutionsListPanel extends ListPanel
{
    /** URL to the API endpoint where items can be retrieved */
    public string $apiUrl = '';

    /** How many items to display on one page in this list */
    public int $count = 30;

    /** Form for adding or editing an institution */
    public ?PKPInstitutionForm $form = null;

    /**Query parameters to pass if this list executes GET requests  */
    public array $getParams = [];

    /** Max number of items available to display in this list panel  */
    public int $itemsMax = 0;

    /**
     * @copydoc ListPanel::getConfig()
     */
    public function getConfig(): array
    {
        $config = parent::getConfig();
        $config = array_merge(
            $config,
            [
                'addInstitutionLabel' => __('grid.action.addInstitution'),
                'apiUrl' => $this->apiUrl,
                'confirmDeleteMessage' => __('manager.institutions.confirmDelete'),
                'count' => $this->count,
                'deleteInstitutionLabel' => __('manager.institutions.deleteInstitution'),
                'editInstitutionLabel' => __('manager.institutions.edit'),
                'form' => $this->form->getConfig(),
                'itemsMax' => $this->itemsMax
            ]
        );
        return $config;
    }
}
