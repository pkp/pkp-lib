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

use APP\i18n\AppLocale;

class PKPContributorsListPanel extends ListPanel
{
    /** @param \PKP\components\forms\publication\PKPContributorForm Form for adding or editing a contributor */
    public $form = null;

    /** @var int Max number of items available to display in this list panel  */
    public $itemsMax = [];

    /** @var bool Indicator of whether the user can edit the current publication */
    public $canEditPublication = false;

    /**
     * Initialize the form with config parameters
     *
     * @param string $id
     * @param string $title
     * @param array $args Configuration params
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
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);

        $config = parent::getConfig();

        // Remove some props not used in this list panel
        unset($config['description']);
        unset($config['expanded']);
        unset($config['headingLevel']);

        $config = array_merge(
            $config,
            [
                'addContributorLabel' => __('grid.action.addContributor'),
                'confirmDeleteMessage' => __('grid.action.deleteContributor.confirmationMessage'),
                'deleteContributorLabel' => __('grid.action.delete'),
                'editContributorLabel' => __('grid.action.edit'),
                'form' => $this->form->getConfig(),
                'canEditPublication' => $this->canEditPublication,
            ]
        );

        return $config;
    }
}
