<?php

/**
 * @file api/v1/stats/sushi/StatsSushiController.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StatsSushiController
 *
 * @ingroup api_v1_stats
 *
 * @brief Handle API requests for COUNTER R5 SUSHI statistics.
 *
 */

namespace APP\API\v1\stats\sushi;

use APP\sushi\IR;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatsSushiController extends \PKP\API\v1\stats\sushi\PKPStatsSushiController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        parent::getGroupRoutes();

        Route::get('reports/ir', $this->getReportsIR(...))
            ->name('stats.sushi.getReportsIR');
    }

    /**
     * COUNTER 'Item Master Report' [IR].
     * A customizable report detailing activity at the pre-print level
     * that allows the user to apply filters and select other configuration options for the report.
     */
    public function getReportsIR(Request $illuminateRequest): JsonResponse|StreamedResponse
    {
        return $this->getReportResponse(new IR(), $illuminateRequest);
    }

    /**
     * Get the application specific list of reports supported by the API
     */
    protected function getReportList(): array
    {
        return array_merge(parent::getReportList(), [
            [
                'Report_Name' => 'Item Master Report',
                'Report_ID' => 'IR',
                'Release' => '5',
                'Report_Description' => __('sushi.reports.ir.description'),
                'Path' => 'reports/ir'
            ],
        ]);
    }
}
