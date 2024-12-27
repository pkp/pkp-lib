<?php

/**
 * @file pages/about/AboutContextHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AboutContextHandler
 *
 * @ingroup pages_about
 *
 * @brief Handle requests for context-level about functions.
 */

namespace PKP\pages\about;

use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use DateTime;
use Illuminate\Support\Collection;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\orcid\OrcidManager;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\Role;
use PKP\userGroup\relationships\enums\UserUserGroupStatus;
use PKP\userGroup\relationships\UserUserGroup;
use PKP\userGroup\UserGroup;

class AboutContextHandler extends Handler
{
    /**
     * @see PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $context = $request->getContext();
        if (!$context || !$context->getData('restrictSiteAccess')) {
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->setCacheability(TemplateManager::CACHEABILITY_PUBLIC);
        }

        $this->addPolicy(new ContextRequiredPolicy($request));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Display about page.
     *
     * @param array $args
     * @param \PKP\core\PKPRequest $request
     */
    public function index($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $templateMgr->display('frontend/pages/about.tpl');
    }


    private function getSortedMastheadUserGroups(Context $context): Collection
    {
        $mastheadUserGroups = UserGroup::withContextIds([$context->getId()])
            ->masthead(true)
            ->excludeRoles([Role::ROLE_ID_REVIEWER])
            ->get();
    
        $savedOrder = (array) $context->getData('mastheadUserGroupIds');
    
        $sortedUserGroups = $mastheadUserGroups->sortBy(function ($userGroup) use ($savedOrder) {
            return array_search($userGroup->id, $savedOrder);
        });
    
        return $sortedUserGroups;
    }
    

    /**
     * Display editorial masthead page.
     *
     * @param array $args
     * @param \PKP\core\PKPRequest $request
     *
     * @hook AboutContextHandler::editorialMasthead [[$mastheadRoles, $mastheadUsers, $reviewers, $previousYear]]
     */
    public function editorialMasthead($args, $request)
    {
        $context = $request->getContext();
    
        // Get sorted masthead roles using the extracted method
        $mastheadRoles = $this->getSortedMastheadUserGroups($context);
    
        // Get all user IDs grouped by user group ID for the masthead roles
        $allUsersIdsGroupedByUserGroupId = Repo::userGroup()->getMastheadUserIdsByRoleIds(
            $mastheadRoles->all(),
            $context->getId()
        );
    
        $mastheadUsers = [];
        foreach ($mastheadRoles as $userGroupId => $mastheadUserGroup) {
            foreach ($allUsersIdsGroupedByUserGroupId[$userGroupId] ?? [] as $userId) {
                $user = Repo::user()->get($userId);
                $userUserGroup = UserUserGroup::withUserId($user->getId())
                    ->withUserGroupIds([$userGroupId])
                    ->withActive()
                    ->withMasthead()
                    ->first();
                if ($userUserGroup) {
                    $startDatetime = new DateTime($userUserGroup->dateStart);
                    $mastheadUsers[$userGroupId][$user->getId()] = [
                        'user' => $user,
                        'dateStart' => $startDatetime->format('Y'),
                    ];
                }
            }
        }

        $previousYear = date('Y') - 1;
        $reviewerIds = Repo::reviewAssignment()->getExternalReviewerIdsByCompletedYear($context->getId(), $previousYear);
        $usersCollector = Repo::user()->getCollector();
        $reviewers = $usersCollector
            ->filterByUserIds($reviewerIds->toArray())
            ->orderBy(
                $usersCollector::ORDERBY_FAMILYNAME,
                $usersCollector::ORDER_DIR_ASC,
                [Locale::getLocale(), Application::get()->getRequest()->getSite()->getPrimaryLocale()]
            )
            ->getMany();

        Hook::call('AboutContextHandler::editorialMasthead', [$mastheadRoles, $mastheadUsers, $reviewers, $previousYear]);

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $templateMgr->assign([
            'mastheadRoles' => $mastheadRoles,
            'mastheadUsers' => $mastheadUsers,
            'reviewers' => $reviewers,
            'previousYear' => $previousYear,
            'orcidIcon' => OrcidManager::getIcon(),
        ]);
        $templateMgr->display('frontend/pages/editorialMasthead.tpl');
    }

    /**
     * Display editorial history page.
     *
     * @param array $args
     * @param \PKP\core\PKPRequest $request
     *
     * @hook AboutContextHandler::editorialHistory [[$mastheadRoles, $mastheadUsers]]
     */
    public function editorialHistory($args, $request)
    {
        $context = $request->getContext();

        // get sorted masthead roles using the extracted method
        $mastheadRoles = $this->getSortedMastheadUserGroups($context);
    
        // get all user IDs grouped by user group ID for the masthead roles with ended status
        $allUsersIdsGroupedByUserGroupId = Repo::userGroup()->getMastheadUserIdsByRoleIds(
            $mastheadRoles->all(),
            $context->getId(),
            UserUserGroupStatus::STATUS_ENDED
        );

        $mastheadUsers = [];
        foreach ($mastheadRoles as $userGroupId => $mastheadUserGroup) {
            foreach ($allUsersIdsGroupedByUserGroupId[$userGroupId] ?? [] as $userId) {
                $user = Repo::user()->get($userId);
                $userUserGroups = UserUserGroup::withUserId($user->getId())
                    ->withUserGroupIds([$userGroupId])
                    ->withEnded()
                    ->withMasthead()
                    ->orderBy('date_start', 'desc')
                    ->get();
                $services = [];
                foreach ($userUserGroups as $userUserGroup) {
                    $startDatetime = new DateTime($userUserGroup->dateStart);
                    $endDatetime = new DateTime($userUserGroup->dateEnd);
                    $services[] = [
                        'dateStart' => $startDatetime->format('Y'),
                        'dateEnd' => $endDatetime->format('Y'),
                    ];
                }
                if (!empty($services)) {
                    $mastheadUsers[$userGroupId][$user->getId()] = [
                        'user' => $user,
                        'services' => $services
                    ];
                }
            }
        }

        Hook::call('AboutContextHandler::editorialHistory', [$mastheadRoles, $mastheadUsers]);

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $templateMgr->assign([
            'mastheadRoles' => $mastheadRoles,
            'mastheadUsers' => $mastheadUsers,
            'orcidIcon' => OrcidManager::getIcon(),
        ]);
        $templateMgr->display('frontend/pages/editorialHistory.tpl');
    }

    /**
     * Display submissions page.
     *
     * @param array $args
     * @param \PKP\core\PKPRequest $request
     */
    public function submissions($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
        $this->setupTemplate($request);


        $templateMgr->assign('submissionChecklist', $context->getLocalizedData('submissionChecklist'));

        // Get sections for this context
        $canSubmitAll = false;
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if ($userRoles && !empty(array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], $userRoles))) {
            $canSubmitAll = true;
        }

        $sections = Repo::section()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->excludeEditorOnly(!$canSubmitAll)
            ->getMany()
            ->all();

        $templateMgr->assign('sections', $sections);
        $templateMgr->display('frontend/pages/submissions.tpl');
    }

    /**
     * Display contact page.
     *
     * @param array $args
     * @param \PKP\core\PKPRequest $request
     */
    public function contact($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $context = $request->getContext();
        $templateMgr->assign([
            'mailingAddress' => $context->getData('mailingAddress'),
            'contactPhone' => $context->getData('contactPhone'),
            'contactEmail' => $context->getData('contactEmail'),
            'contactName' => $context->getData('contactName'),
            'supportName' => $context->getData('supportName'),
            'supportPhone' => $context->getData('supportPhone'),
            'supportEmail' => $context->getData('supportEmail'),
            'contactTitle' => $context->getLocalizedData('contactTitle'),
            'contactAffiliation' => $context->getLocalizedData('contactAffiliation'),
        ]);
        $templateMgr->display('frontend/pages/contact.tpl');
    }
}
