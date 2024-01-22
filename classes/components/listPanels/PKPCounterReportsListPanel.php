<?php
/**
 * @file classes/components/listPanels/PKPCounterReportsListPanel.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCounterReportsListPanel
 *
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for viewing, editing and downloading COUNTER R5 reports
 */

namespace PKP\components\listPanels;

use APP\components\forms\counter\CounterReportForm;
use PKP\sushi\CounterR5Report;

class PKPCounterReportsListPanel extends ListPanel
{
    /** URL to the API endpoint where items can be retrieved */
    public string $apiUrl = '';

    /** Form for setting up and downloading a report*/
    public ?CounterReportForm $form = null;

    /** Query parameters to pass if this list executes GET requests  */
    public array $getParams = [];

    /**
     * @copydoc ListPanel::getConfig()
     */
    public function getConfig(): array
    {
        $config = parent::getConfig();

        $earliestDate = CounterR5Report::getEarliestDate();
        $lastDate = CounterR5Report::getLastDate();

        $config = array_merge(
            $config,
            [
                'apiUrl' => $this->apiUrl,
                'editCounterReportLabel' => __('manager.statistics.counterR5Report.settings'),
                'form' => $this->form->getConfig(),
                'usagePossible' => $lastDate > $earliestDate,
            ]
        );
        return $config;
    }
}
