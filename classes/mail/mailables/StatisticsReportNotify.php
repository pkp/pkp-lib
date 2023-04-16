<?php

/**
 * @file classes/mail/mailables/AnnouncementNotify.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StatisticsReportNotify
 *
 * @brief Email sent to notify users about new announcement
 */

namespace PKP\mail\mailables;

use APP\core\Application;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Unsubscribe;
use PKP\security\Role;

class StatisticsReportNotify extends Mailable
{
    use Recipient;
    use Configurable;
    use Unsubscribe;

    protected static ?string $name = 'mailable.statisticsReportNotify.name';
    protected static ?string $description = 'mailable.statisticsReportNotify.description';
    protected static ?string $emailTemplateKey = 'STATISTICS_REPORT_NOTIFICATION';
    protected static bool $canDisable = true;
    protected static array $groupIds = [self::GROUP_OTHER];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_SUB_EDITOR];

    protected Context $context;

    public function __construct(
        Context $context,
        array $editorialTrends,
        int $totalSubmissions,
        string $month,
        int $year
    ) {
        parent::__construct([$context]);
        $this->context = $context;
        $this->setupStatisticsVariables($context, $editorialTrends, $totalSubmissions, $month, $year);
    }

    public static function getDataDescriptions(): array
    {
        $variables = [
            'newSubmissions' => __('emailTemplate.variable.statisticsReportNotify.newSubmissions'),
            'declinedSubmissions' => __('emailTemplate.variable.statisticsReportNotify.declinedSubmissions'),
            'acceptedSubmissions' => __('emailTemplate.variable.statisticsReportNotify.acceptedSubmissions'),
            'skippedSubmissions' => __('emailTemplate.variable.statisticsReportNotify.otherSubmissions'),
            'totalSubmissions' => __('emailTemplate.variable.statisticsReportNotify.totalSubmissions'),
            'month' => __('emailTemplate.variable.statisticsReportNotify.month'),
            'year' => __('emailTemplate.variable.statisticsReportNotify.year'),
            'editorialStatsLink' => __('emailTemplate.variable.statisticsReportNotify.editorialStatsLink'),
            'publicationStatsLink' => __('emailTemplate.variable.statisticsReportNotify.publicationStatsLink'),
        ];

        return array_merge(parent::getDataDescriptions(), $variables);
    }

    protected function setupStatisticsVariables(
        Context $context,
        array $editorialTrends,
        int $totalSubmissions,
        string $month,
        int $year
    ): void {
        $dispatcher = Application::get()->getDispatcher();
        $request = Application::get()->getRequest();

        $trends = [];
        foreach ($editorialTrends as $stat) {
            $trends[$stat['key']] = $stat['value'];
        }

        ['submissionsReceived' => $newSubmissions,
            'submissionsDeclined' => $declinedSubmissions,
            'submissionsAccepted' => $acceptedSubmissions,
            'submissionsSkipped' => $skippedSubmissions
        ] = $trends;

        $this->addData([
            'newSubmissions' => $newSubmissions,
            'declinedSubmissions' => $declinedSubmissions,
            'acceptedSubmissions' => $acceptedSubmissions ?? null,
            'skippedSubmissions' => $skippedSubmissions,
            'totalSubmissions' => $totalSubmissions,
            'month' => $month,
            'year' => $year,
            'editorialStatsLink' => $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'stats', 'editorial'),
            'publicationStatsLink' => $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'stats', 'publications')
        ]);
    }

    /**
     * Adds a footer with unsubscribe link
     */
    protected function addFooter(string $locale): Mailable
    {
        $this->setupUnsubscribeFooter($locale, $this->context);
        return $this;
    }
}
