<?php

/**
 * @file classes/controllers/grid/DateGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DateGridCellProvider
 *
 * @ingroup controllers_grid
 *
 * @brief Wraps date formatting support around a provided DataProvider.
 */

namespace PKP\controllers\grid;

use PKP\core\PKPString;
use PKP\facades\Locale;

class DateGridCellProvider extends GridCellProvider
{
    /** @var GridCellProvider The actual data provider to wrap */
    public $_dataProvider;

    /** @var string The format to use; see DateTime::format */
    public $_format;

    /**
     * Constructor
     *
     * @param GridCellProvider $dataProvider The object to wrap
     * @param string $format See DateTime::format
     */
    public function __construct($dataProvider, $format)
    {
        parent::__construct();
        $this->_dataProvider = $dataProvider;
        $this->_format = PKPString::convertStrftimeFormat($format);
    }

    //
    // Template methods from GridCellProvider
    //
    /**
     * Fetch a value from the provided DataProvider (in constructor)
     * and format it as a date.
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $v = $this->_dataProvider->getTemplateVarsFromRowColumn($row, $column);
        $v['label'] = (new \Carbon\Carbon($v['label']))->locale(Locale::getLocale())->translatedFormat($this->_format);
        return $v;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\DateGridCellProvider', '\DateGridCellProvider');
}
