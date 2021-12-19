<?php
/**
 * @file components/PKPStatsJobsTable.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsJobsTable
 * @ingroup classes_components_stats
 *
 * @brief A class to prepare the data object for a jobs table UI component
 */

namespace PKP\components;

use APP\i18n\AppLocale;

class PKPStatsJobsTable
{
    /** @var string An ID for this component  */
    public $id = '';

    /** @var array Configuration for the columns to display in the table */
    public $tableColumns = [];

    /** @var array Rows to display in the table */
    public $tableRows = [];

    /** @var null|string Table description */
    public $description = null;

    /** @var null|string Table label */
    public $label = null;

    /**
     * Constructor
     *
     * @param array $args Optional arguments
     */
    public function __construct(
        string $id,
        array $args = []
    ) {
        AppLocale::requireComponents([
            LOCALE_COMPONENT_PKP_MANAGER,
            LOCALE_COMPONENT_APP_MANAGER
        ]);

        $this->id = $id;
        $this->init($args);
    }

    /**
     * Initialize the handler with config parameters
     *
     * @param array $args Configuration params
     */
    public function init(array $args = []): void
    {
        foreach ($args as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }

            $this->{$key} = $value;
        }
    }

    /**
     * Retrieve the configuration data to be used when initializing this
     * handler on the frontend
     *
     * @return array Configuration data
     */
    public function getConfig(): array
    {
        $config = [
            'columns' => $this->tableColumns,
            'rows' => $this->tableRows,
        ];

        if ($this->description) {
            $config['description'] = $this->description;
        }

        if ($this->label) {
            $config['label'] = $this->label;
        }

        return $config;
    }
}
