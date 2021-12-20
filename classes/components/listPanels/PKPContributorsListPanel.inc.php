<?php
/**
 * @file classes/components/listPanels/PKPContributorsListPanel.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContributorsListPanel
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for viewing and editing contributors
 */

namespace PKP\components\listPanels;

class PKPContributorsListPanel extends ListPanel
{
    /** @var int How many items to display on one page in this list */
    public $count = 30;

    /** @param \PKP\components\forms\publication\PKPContributorForm Form for adding or editing a contributor */
    public $form = null;

    /** @var array Query parameters to pass if this list executes GET requests  */
    public $getParams = [];

    /** @var int Max number of items available to display in this list panel  */
    public $itemsMax = [];

    /** @var bool Indicator of whether the user can edit the current publication */
    public $canEditPublication = false;

    /**
     * Initialize the form with config parameters
     *
     * @param $id string
     * @param $title string
     * @param $args array Configuration params
     */
    public function __construct($id, $title, $args = [])
    {
        parent::__construct($id, $title, $args);
    }

    /**
     * @copydoc ListPanel::getConfig()
     */
    public function getConfig()
    {
        \AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);
        \AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);
        $request = \Application::get()->getRequest();

        $config = parent::getConfig();

        $config = array_merge(
            $config,
            [
                'addContributorLabel' => __('grid.action.addContributor'),
                'confirmDeleteMessage' => __('grid.action.deleteContributor.confirmationMessage'),
                'count' => $this->count,
                'deleteContributorLabel' => __('grid.action.delete'),
                'editContributorLabel' => __('grid.action.edit'),
                'form' => $this->form->getConfig(),
                'canEditPublication' => $this->canEditPublication,
            ]
        );

        return $config;
    }
}
