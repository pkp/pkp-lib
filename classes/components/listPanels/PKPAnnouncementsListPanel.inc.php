<?php
/**
 * @file classes/components/listPanels/PKPAnnouncementsListPanel.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementsListPanel
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for viewing and editing email templates
 */

namespace PKP\components\listPanels;

class PKPAnnouncementsListPanel extends ListPanel
{
    /** @var string URL to the API endpoint where items can be retrieved */
    public $apiUrl = '';

    /** @var int How many items to display on one page in this list */
    public $count = 30;

    /** @param \PKP\components\forms\announcement\PKPAnnouncementForm Form for adding or editing an email template */
    public $form = null;

    /** @var array Query parameters to pass if this list executes GET requests  */
    public $getParams = [];

    /** @var int Max number of items available to display in this list panel  */
    public $itemsMax = [];

    /**
     * @copydoc ListPanel::getConfig()
     */
    public function getConfig()
    {
        $request = \Application::get()->getRequest();
        return parent::getConfig() + [
            'addAnnouncementLabel' => __('grid.action.addAnnouncement'),
            'apiUrl' => $this->apiUrl,
            'confirmDeleteMessage' => __('manager.announcements.confirmDelete'),
            'count' => $this->count,
            'deleteAnnouncementLabel' => __('manager.announcements.deleteAnnouncement'),
            'editAnnouncementLabel' => __('manager.announcements.edit'),
            'form' => $this->form->getConfig(),
            'itemsMax' => $this->itemsMax,
            'urlBase' => $request->getDispatcher()->url(
                $request,
                \PKPApplication::ROUTE_PAGE,
                $request->getContext()->getPath(),
                'announcement',
                'view',
                '__id__'
            )
        ];
    }
}
