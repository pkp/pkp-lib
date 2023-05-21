<?php

/**
 * @file classes/controllers/listbuilder/ListbuilderGridColumn.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ListbuilderGridColumn
 *
 * @ingroup controllers_listbuilder
 *
 * @brief Represents a column within a listbuilder.
 */

namespace PKP\controllers\listbuilder;

use PKP\controllers\grid\GridColumn;
use PKP\controllers\listbuilder\users\UserListbuilderGridCellProvider;

class ListbuilderGridColumn extends GridColumn
{
    /**
     * Constructor
     *
     * @param ListbuilderHandler $listbuilder The listbuilder handler this column belongs to.
     * @param string $id The optional symbolic ID for this column.
     * @param string $title The optional title for this column.
     * @param string $titleTranslated The optional translated title for this column.
     * @param string $template The optional overridden template for this column.
     * @param UserListbuilderGridCellProvider $cellProvider The optional overridden grid cell provider.
     * @param array $flags Optional set of flags for this column's display.
     */
    public function __construct(
        $listbuilder,
        $id = '',
        $title = null,
        $titleTranslated = null,
        $template = null,
        $cellProvider = null,
        $flags = []
    ) {
        // Set this here so that callers using later optional parameters don't need to
        // duplicate it.
        if ($template === null) {
            $template = 'controllers/listbuilder/listbuilderGridCell.tpl';
        }

        // Make the listbuilder's source type available to the cell template as a flag
        $flags['sourceType'] = $listbuilder->getSourceType();
        parent::__construct($id, $title, $titleTranslated, $template, $cellProvider, $flags);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\listbuilder\ListbuilderGridColumn', '\ListbuilderGridColumn');
}
