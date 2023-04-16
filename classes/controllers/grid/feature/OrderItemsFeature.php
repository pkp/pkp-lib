<?php

/**
 * @file classes/controllers/grid/feature/OrderItemsFeature.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrderItemsFeature
 *
 * @ingroup controllers_grid_feature
 *
 * @brief Base class for grid widgets ordering functionality.
 *
 */

namespace PKP\controllers\grid\feature;

use APP\template\TemplateManager;
use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;

use PKP\linkAction\request\NullAction;

class OrderItemsFeature extends GridFeature
{
    /** @var bool */
    public $_overrideRowTemplate;

    /** @var string */
    public $_nonOrderableItemMessage;

    /**
     * Constructor.
     *
     * @param bool $overrideRowTemplate This feature uses row
     * actions and it will force the usage of the gridRow.tpl.
     * If you want to use a different grid row template file, set this flag to
     * false and make sure to use a template file that adds row actions.
     * @param string $nonOrderableItemMessage optional A translated message to be used
     * when user tries to move a non orderable grid item.
     */
    public function __construct($overrideRowTemplate, $nonOrderableItemMessage = null)
    {
        parent::__construct('orderItems');

        $this->setOverrideRowTemplate($overrideRowTemplate);
        $this->setNonOrderableItemMessage($nonOrderableItemMessage);
    }


    //
    // Getters and setters.
    //
    /**
     * Set override row template flag.
     */
    public function setOverrideRowTemplate($overrideRowTemplate)
    {
        $this->_overrideRowTemplate = $overrideRowTemplate;
    }

    /**
     * Get override row template flag.
     *
     * @param GridRow $gridRow
     *
     * @return bool
     */
    public function getOverrideRowTemplate(&$gridRow)
    {
        // Make sure we don't return the override row template
        // flag to objects that are not instances of GridRow class.
        if ($gridRow instanceof GridRow) {
            return $this->_overrideRowTemplate;
        } else {
            return false;
        }
    }

    /**
     * Set non orderable item message.
     *
     * @param string $nonOrderableItemMessage Message already translated.
     */
    public function setNonOrderableItemMessage($nonOrderableItemMessage)
    {
        $this->_nonOrderableItemMessage = $nonOrderableItemMessage;
    }

    /**
     * Get non orderable item message.
     *
     * @return string Message already translated.
     */
    public function getNonOrderableItemMessage()
    {
        return $this->_nonOrderableItemMessage;
    }


    //
    // Extended methods from GridFeature.
    //
    /**
     * @see GridFeature::setOptions()
     */
    public function setOptions($request, $grid)
    {
        parent::setOptions($request, $grid);

        $router = $request->getRouter();
        $this->addOptions([
            'saveItemsSequenceUrl' => $router->url($request, null, null, 'saveSequence', null, $grid->getRequestArgs()),
            'csrfToken' => $request->getSession()->getCsrfToken(),
        ]);
    }

    /**
     * @see GridFeature::fetchUIElements()
     */
    public function fetchUIElements($request, $grid)
    {
        $templateMgr = TemplateManager::getManager($request);
        $UIElements = [];
        if ($this->isOrderActionNecessary()) {
            $templateMgr->assign('gridId', $grid->getId());
            $UIElements['orderFinishControls'] = $templateMgr->fetch('controllers/grid/feature/gridOrderFinishControls.tpl');
        }
        $nonOrderableItemMessage = $this->getNonOrderableItemMessage();
        if ($nonOrderableItemMessage) {
            $templateMgr->assign('orderMessage', $nonOrderableItemMessage);
            $UIElements['orderMessage'] = $templateMgr->fetch('controllers/grid/feature/gridOrderNonOrderableMessage.tpl');
        }

        return $UIElements;
    }


    //
    // Hooks implementation.
    //
    /**
     * @see GridFeature::getInitializedRowInstance()
     */
    public function getInitializedRowInstance($args)
    {
        $row = & $args['row'];
        if ($args['grid']->getDataElementSequence($row->getData()) !== false) {
            $this->addRowOrderAction($row);
        }
    }

    /**
     * @see GridFeature::gridInitialize()
     */
    public function gridInitialize($args)
    {
        $grid = & $args['grid'];

        if ($this->isOrderActionNecessary()) {
            $grid->addAction(
                new LinkAction(
                    'orderItems',
                    new NullAction(),
                    __('grid.action.order'),
                    'order_items'
                )
            );
        }
    }


    //
    // Protected methods.
    //
    /**
     * Add grid row order action.
     *
     * @param GridRow $row
     */
    public function addRowOrderAction($row)
    {
        if ($this->getOverrideRowTemplate($row)) {
            $row->setTemplate('controllers/grid/gridRow.tpl');
        }

        $row->addAction(
            new LinkAction(
                'moveItem',
                new NullAction(),
                '',
                'order_items'
            ),
            GridRow::GRID_ACTION_POSITION_ROW_LEFT
        );
    }

    //
    // Protected template methods.
    //
    /**
     * Return if this feature will use
     * a grid level order action. Default is
     * true, override it if needed.
     *
     * @return bool
     */
    public function isOrderActionNecessary()
    {
        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\feature\OrderItemsFeature', '\OrderItemsFeature');
}
