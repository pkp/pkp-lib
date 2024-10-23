<?php
/**
 * @file classes/submission/maps/Schema.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map submissions to the properties defined in the submission schema
 */

namespace APP\submission\maps;

use APP\core\Application;
use APP\decision\types\Decline;
use APP\decision\types\RevertDecline;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Collection;
use PKP\decision\DecisionType;
use PKP\plugins\Hook;
use PKP\security\Role;

class Schema extends \PKP\submission\maps\Schema
{
    /**
     * @copydoc \PKP\submission\maps\Schema::mapByProperties()
     */
    protected function mapByProperties(array $props, Submission $submission, bool|Collection $anonymizeReviews = false): array
    {
        $output = parent::mapByProperties($props, $submission, $anonymizeReviews);
        if (in_array('urlPublished', $props)) {
            $output['urlPublished'] = $this->request->getDispatcher()->url(
                $this->request,
                Application::ROUTE_PAGE,
                $this->context->getPath(),
                'preprint',
                'view',
                [$submission->getBestId()]
            );
        }

        $locales = $this->context->getSupportedSubmissionMetaDataLocales();

        if (!in_array($submissionLocale = $submission->getData('locale'), $locales)) {
            $locales[] = $submissionLocale;
        }

        $output = $this->schemaService->addMissingMultilingualValues($this->schemaService::SCHEMA_SUBMISSION, $output, $locales);

        ksort($output);

        return $this->withExtensions($output, $submission);
    }


    /**
     * Gets the Editorial decisions available to editors for a given stage of a submission
     *
     * This method returns decisions only for active stages. For inactive stages, it returns an empty array.
     *
     * @return DecisionType[]
     *
     * @hook Workflow::Decisions [[&$decisionTypes, $stageId]]
     */
    protected function getAvailableEditorialDecisions(int $stageId, Submission $submission): array
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $isActiveStage = $submission->getData('stageId') == $stageId;
        $userHasAccessibleRoles = $user->hasRole([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_ASSISTANT], $request->getContext()->getId());
        $permissions = $this->checkDecisionPermissions($stageId, $submission, $user, $request->getContext()->getId());

        /** Only the production stage is supported in OPS.*/
        if ($stageId !== WORKFLOW_STAGE_ID_PRODUCTION || !$userHasAccessibleRoles || !$isActiveStage || !$permissions['canMakeDecision']) {
            return [];
        }

        $isOnlyRecommending = $permissions['isOnlyRecommending'];
        $decisionTypes = []; /** @var DecisionType[] $decisionTypes */

        if ($isOnlyRecommending) {
            $decisionTypes[] = Repo::decision()->getDecisionTypesMadeByRecommendingUsers($stageId);
        } else {
            switch ($submission->getData('status')) {
                case Submission::STATUS_DECLINED:
                    $decisionTypes[] = new RevertDecline();
                    break;
                case Submission::STATUS_QUEUED:
                    $decisionTypes[] = new Decline();
                    break;
            }
        }

        Hook::call('Workflow::Decisions', [&$decisionTypes, $stageId]);

        return $decisionTypes;
    }
}
