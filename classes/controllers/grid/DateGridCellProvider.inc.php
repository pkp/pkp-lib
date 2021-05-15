<?php

/**
 * @file classes/controllers/grid/DateGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DateGridCellProvider
 * @ingroup controllers_grid
 *
 * @brief Wraps date formatting support around a provided DataProvider.
 */

namespace PKP\controllers\grid;

class DateGridCellProvider extends GridCellProvider
{
    /** @var The actual data provider to wrap */
    public $_dataProvider;

    /** @var The format to use; see strftime */
    public $_format;

    /**
     * Constructor
     *
     * @param $dataProvider DataProvider The object to wrap
     * @param $format string See strftime
     */
    public function __construct($dataProvider, $format)
    {
        parent::__construct();
        $this->_dataProvider = $dataProvider;
        $this->_format = $format;
    }

    //
    // Template methods from GridCellProvider
    //
    /**
     * Fetch a value from the provided DataProvider (in constructor)
     * and format it as a date.
     *
     * @param $row \PKP\controllers\grid\GridRow
     * @param $column GridColumn
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $v = $this->_dataProvider->getTemplateVarsFromRowColumn($row, $column);
        $v['label'] = strftime($this->_format, strtotime($v['label']));
        return $v;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\DateGridCellProvider', '\DateGridCellProvider');
}
