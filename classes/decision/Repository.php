<?php
/**
 * @file classes/decision/Repository.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A repository to find and manage editorial decisions.
 */

namespace APP\decision;

use APP\decision\types\Decline;
use APP\decision\types\RevertDecline;
use Illuminate\Database\Eloquent\Collection;
use PKP\decision\types\InitialDecline;
use PKP\plugins\Hook;

class Repository extends \PKP\decision\Repository
{
    /** The valid decision types */
    protected ?Collection $decisionTypes;

    public function getDecisionTypes(): Collection
    {
        if (!isset($this->decisionTypes)) {
            $decisionTypes = new Collection([
                new Decline(),
                new RevertDecline(),
            ]);
            Hook::call('Decision::types', [$decisionTypes]);
            $this->decisionTypes = $decisionTypes;
        }

        return $this->decisionTypes;
    }

    public function getDeclineDecisionTypes(): array
    {
        return [
            new InitialDecline(),
        ];
    }

    /** OPS does not support recommendations */
    public function isRecommendation(int $decision): bool
    {
        return false;
    }

    /** OPS does not support review rounds */
    protected function getReviewNotificationTypes(): array
    {
        return [];
    }
}
