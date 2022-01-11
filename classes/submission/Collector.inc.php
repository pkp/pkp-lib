<?php
/**
 * @file classes/submission/Collector.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submission
 *
 * @brief A helper class to configure a Query Builder to get a collection of submissions
 */

namespace APP\submission;

use Illuminate\Database\Query\Builder;
use PKP\doi\Doi;

class Collector extends \PKP\submission\Collector
{
    /**  */
    public ?array $sectionIds = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /**
     * Limit results to submissions assigned to these sections
     */
    public function filterBySectionIds(array $sectionIds): self
    {
        $this->sectionIds = $sectionIds;
        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $q = parent::getQueryBuilder();

        if (is_array($this->sectionIds)) {
            $sectionIds = $this->sectionIds;
            $q->leftJoin('publications as section_p', 'section_p.submission_id', '=', 's.submission_id')
                ->whereIn('section_p.section_id', $sectionIds);
        }

        return $q;
    }

    /**
     * Add APP-specific filtering methods for submission sub objects DOI statuses
     *
     */
    protected function addDoiStatusFilterToQuery(Builder $q)
    {
        $q->whereIn('s.current_publication_id', function (Builder $q) {
            $q->select('current_p.publication_id')
                ->from('publications as current_p')
                ->leftJoin('publication_galleys as current_g', 'current_g.publication_id', '=', 'current_p.publication_id')
                ->leftJoin('dois as pd', 'pd.doi_id', '=', 'current_p.doi_id')
                ->leftJoin('dois as gd', 'gd.doi_id', '=', 'current_g.doi_id')
                ->whereIn('pd.status', $this->doiStatuses)
                ->orWhereIn('gd.status', $this->doiStatuses);

            $q->when(
                (in_array(Doi::STATUS_UNREGISTERED, $this->doiStatuses) && !$this->strictDoiStatusFilter),
                function (Builder $q) {
                    $q->orWhereNull('pd.status')
                        ->orWhereNull('gd.status');
                }
            );
        });
    }
}
