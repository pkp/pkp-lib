<?php

/**
 * @file controllers/grid/pubIds/PubIdExportSubmissionsListGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PubIdExportSubmissionsListGridCellProvider
 * @ingroup controllers_grid_pubIds
 *
 * @brief Class for a cell provider that can retrieve labels from submissions with pub ids
 */

namespace APP\controllers\grid\pubIds;

use APP\controllers\grid\submissions\ExportPublishedSubmissionsListGridCellProvider;

class PubIdExportSubmissionsListGridCellProvider extends ExportPublishedSubmissionsListGridCellProvider
{
    /**
     * @copydoc ExportPublishedSubmissionsListGridCellProvider::getTemplateVarsFromRowColumn()
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $submission = $row->getData();
        $columnId = $column->getId();
        assert($submission instanceof \APP\submission\Submission && !empty($columnId));

        switch ($columnId) {
            case 'pubId':
                $storedPubIds = [];
                foreach ($submission->getData('publications') as $publication) {
                    if ($publication->getStoredPubId($this->_plugin->getPubIdType())) {
                        $storedPubIds[] = $publication->getStoredPubId($this->_plugin->getPubIdType());
                    }
                }
                $storedPubIds = array_unique($storedPubIds);
                return ['label' => implode(' ', $storedPubIds)];
        }
        return parent::getTemplateVarsFromRowColumn($row, $column);
    }
}
