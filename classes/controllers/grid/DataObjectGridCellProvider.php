<?php

/**
 * @file classes/controllers/grid/DataObjectGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataObjectGridCellProvider
 * @ingroup controllers_grid
 *
 * @brief Base class for a cell provider that can retrieve simple labels
 *  from DataObjects. If you need more complex cell content then you may
 *  be better off using a ColumnBasedGridCellProvider.
 *
 * @see ColumnBasedGridCellProvider
 */

namespace PKP\controllers\grid;

use PKP\facades\Locale;

class DataObjectGridCellProvider extends GridCellProvider
{
    /** @var string the locale to be retrieved. */
    public $_locale = null;


    //
    // Setters and Getters
    //
    /**
     * Set the locale
     *
     * @param string $locale
     */
    public function setLocale($locale)
    {
        $this->_locale = $locale;
    }

    /**
     * Get the locale
     *
     * @return string
     */
    public function getLocale()
    {
        if (empty($this->_locale)) {
            return Locale::getLocale();
        }
        return $this->_locale;
    }


    //
    // Template methods from GridCellProvider
    //
    /**
     * This implementation assumes an element that is a
     * DataObject. It will retrieve an element in the
     * configured locale.
     *
     * @see GridCellProvider::getTemplateVarsFromRowColumn()
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $element = $row->getData();
        $columnId = $column->getId();
        assert($element instanceof \PKP\core\DataObject && !empty($columnId));

        $data = $element->getData($columnId);
        // For localized fields, $data will be an array; otherwise,
        // it will be a value suitable for conversion to string.
        // If it's localized, fetch the value in the current locale.
        if (is_array($data)) {
            $data = $element->getLocalizedData($columnId);
        }

        return ['label' => $data];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\DataObjectGridCellProvider', '\DataObjectGridCellProvider');
}
