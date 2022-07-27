<?php

/**
 * @file classes/controllers/grid/feature/InfiniteScrollingFeature.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InfiniteScrollingFeature
 * @ingroup controllers_grid_feature
 *
 * @brief Add infinite scrolling functionality to grids. It doesn't support
 * category grids.
 *
 */

namespace PKP\controllers\grid\feature;

use APP\template\TemplateManager;
use PKP\linkAction\LinkAction;

use PKP\linkAction\request\NullAction;

class InfiniteScrollingFeature extends GeneralPagingFeature
{
    /**
     * @copydoc GeneralPagingFeature::GeneralPagingFeature()
     * Constructor.
     *
     * @param null|mixed $itemsPerPage
     */
    public function __construct($id = 'infiniteScrolling', $itemsPerPage = null)
    {
        parent::__construct($id, $itemsPerPage);
    }


    //
    // Extended methods from GridFeature.
    //
    /**
     * @copydoc GridFeature::getJSClass()
     */
    public function getJSClass()
    {
        return '$.pkp.classes.features.InfiniteScrollingFeature';
    }

    /**
     * @copydoc GridFeature::fetchUIElements()
     */
    public function fetchUIElements($request, $grid)
    {
        $options = $this->getOptions();

        $shown = $options['currentItemsPerPage'] * $options['currentPage'];
        if ($shown > $options['itemsTotal']) {
            $shown = $options['itemsTotal'];
        }

        $moreItemsLinkAction = false;
        if ($shown < $options['itemsTotal']) {
            $moreItemsLinkAction = new LinkAction(
                'moreItems',
                new NullAction(),
                __('grid.action.moreItems'),
                'more_items'
            );
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'iterator' => $this->getItemIterator(),
            'shown' => $shown,
            'grid' => $grid,
            'moreItemsLinkAction' => $moreItemsLinkAction,
        ]);

        return [
            'pagingMarkup' => $templateMgr->fetch('controllers/grid/feature/infiniteScrolling.tpl'),
        ];
    }


    //
    // Hooks implementation.
    //
    /**
     * @copydoc GridFeature::fetchRows()
     */
    public function fetchRows($args)
    {
        $request = $args['request'];
        $grid = $args['grid'];
        $jsonMessage = $args['jsonMessage'];

        // Render the paging options, including updated markup.
        $this->setOptions($request, $grid);
        $pagingAttributes = ['pagingInfo' => $this->getOptions()];

        // Add paging attributes to json so grid can update UI.
        $additionalAttributes = (array) $jsonMessage->getAdditionalAttributes();
        $jsonMessage->setAdditionalAttributes(
            array_merge(
                $pagingAttributes,
                $additionalAttributes
            )
        );
    }

    /**
     * @copydoc GridFeature::fetchRow()
     * Check if user really deleted a row and fetch the last one from
     * the current page.
     */
    public function fetchRow($args)
    {
        $request = $args['request'];
        $grid = $args['grid'];
        $row = $args['row'];
        $jsonMessage = $args['jsonMessage'];
        $pagingAttributes = [];

        // Render the paging options, including updated markup.
        $this->setOptions($request, $grid);
        $pagingAttributes['pagingInfo'] = $this->getOptions();

        if (is_null($row)) {
            $gridData = $grid->getGridDataElements($request);

            // Get the last data element id of the current page.
            end($gridData);
            $lastRowId = key($gridData);

            // Get the row and render it.
            $args = ['rowId' => $lastRowId];
            $row = $grid->getRequestedRow($request, $args);
            $pagingAttributes['deletedRowReplacement'] = $grid->renderRow($request, $row);
        } else {
            // No need for paging markup.
            unset($pagingAttributes['pagingInfo']['pagingMarkup']);
        }

        // Add paging attributes to json so grid can update UI.
        $additionalAttributes = $jsonMessage->getAdditionalAttributes();

        // Unset sequence map until we support that.
        unset($additionalAttributes['sequenceMap']);
        $jsonMessage->setAdditionalAttributes(
            array_merge(
                $pagingAttributes,
                $additionalAttributes
            )
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\feature\InfiniteScrollingFeature', '\InfiniteScrollingFeature');
}
