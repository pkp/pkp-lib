<?php

/**
 * @file classes/controllers/listbuilder/ListbuilderGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ListbuilderGridRow
 *
 * @ingroup controllers_listbuilder
 *
 * @brief Handle list builder row requests.
 */

namespace PKP\controllers\listbuilder;

use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\NullAction;

class ListbuilderGridRow extends GridRow
{
    /** @var bool */
    public $_hasDeleteItemLink;

    /**
     * Constructor
     *
     * @param bool $hasDeleteItemLink
     */
    public function __construct($hasDeleteItemLink = true)
    {
        parent::__construct();

        $this->setHasDeleteItemLink($hasDeleteItemLink);
    }

    /**
     * Add a delete item link action or not.
     *
     * @param bool $hasDeleteItemLink
     */
    public function setHasDeleteItemLink($hasDeleteItemLink)
    {
        $this->_hasDeleteItemLink = $hasDeleteItemLink;
    }


    //
    // Overridden template methods
    //
    /**
     * @copydoc GridRow::initialize()
     */
    public function initialize($request, $template = 'controllers/listbuilder/listbuilderGridRow.tpl')
    {
        parent::initialize($request);

        // Set listbuilder row template
        $this->setTemplate($template);

        if ($this->_hasDeleteItemLink) {
            // Add deletion action (handled in JS-land)
            $this->addAction(
                new LinkAction(
                    'delete',
                    new NullAction(),
                    '',
                    'remove_item'
                )
            );
        }
    }

    /**
     * @see GridRow::addAction()
     */
    public function addAction($action, $position = GridRow::GRID_ACTION_POSITION_ROW_LEFT)
    {
        return parent::addAction($action, $position);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\listbuilder\ListbuilderGridRow', '\ListbuilderGridRow');
}
