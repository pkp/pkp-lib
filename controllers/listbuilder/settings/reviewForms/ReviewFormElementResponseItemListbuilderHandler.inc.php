<?php
/**
 * @file controllers/listbuilder/settings/reviewForms/ReviewFormElementResponseItemListbuilderHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormElementResponseItemListbuilderHandler
 * @ingroup controllers_listbuilder_settings_reviewForms
 *
 * @brief Review form element response item listbuilder handler
 */

import('lib.pkp.controllers.listbuilder.settings.SetupListbuilderHandler');

use APP\template\TemplateManager;
use PKP\controllers\listbuilder\ListbuilderHandler;

use PKP\controllers\listbuilder\MultilingualListbuilderGridColumn;

class ReviewFormElementResponseItemListbuilderHandler extends SetupListbuilderHandler
{
    /** @var int Review form element ID */
    public $_reviewFormElementId;


    //
    // Overridden template methods
    //
    /**
     * @copydoc SetupListbuilderHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);
        $this->_reviewFormElementId = (int) $request->getUserVar('reviewFormElementId');

        // Basic configuration
        $this->setTitle('grid.reviewFormElement.responseItems');
        $this->setSourceType(ListbuilderHandler::LISTBUILDER_SOURCE_TYPE_TEXT);
        $this->setSaveType(ListbuilderHandler::LISTBUILDER_SAVE_TYPE_EXTERNAL);
        $this->setSaveFieldName('possibleResponses');

        // Possible response column
        $responseColumn = new MultilingualListbuilderGridColumn($this, 'possibleResponse', 'manager.reviewFormElements.possibleResponse', null, null, null, null, ['tabIndex' => 1]);
        import('lib.pkp.controllers.listbuilder.settings.reviewForms.ReviewFormElementResponseItemListbuilderGridCellProvider');
        $responseColumn->setCellProvider(new ReviewFormElementResponseItemListbuilderGridCellProvider());
        $this->addColumn($responseColumn);
    }

    /**
     * @copydoc GridHandler::loadData()
     *
     * @param null|mixed $filter
     */
    protected function loadData($request, $filter = null)
    {
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
        $reviewFormElement = $reviewFormElementDao->getById($this->_reviewFormElementId);
        $formattedResponses = [];
        if ($reviewFormElement) {
            $possibleResponses = $reviewFormElement->getPossibleResponses(null);
            foreach ((array) $possibleResponses as $locale => $values) {
                foreach ($values as $rowId => $value) {
                    // WARNING: Listbuilders don't like 0 row IDs; offsetting
                    // by 1. This is reversed in the saving code.
                    $formattedResponses[$rowId + 1][0]['content'][$locale] = $value;
                }
            }
        }
        return $formattedResponses;
    }

    /**
     * @copydoc GridHandler::getRowDataElement
     */
    protected function getRowDataElement($request, &$rowId)
    {
        // Fallback on the parent if an existing rowId is found
        if (!empty($rowId)) {
            return parent::getRowDataElement($request, $rowId);
        }

        // If we're bouncing a row back upon a row edit
        $rowData = $this->getNewRowId($request);
        if ($rowData) {
            return [['content' => $rowData['possibleResponse']]];
        }

        // If we're generating an empty row to edit
        return [['content' => []]];
    }

    /**
     * @copydoc ListbuilderHandler::fetch()
     */
    public function fetch($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('availableOptions', true);
        return $this->fetchGrid($args, $request);
    }
}
