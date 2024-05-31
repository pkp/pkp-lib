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
use DateInterval;
use DateTime;
use Illuminate\Support\Facades\Cache;
use PKP\facades\Locale;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\Role;
use PKP\userGroup\relationships\enums\UserUserGroupMastheadStatus;
use PKP\userGroup\relationships\enums\UserUserGroupStatus;
use PKP\userGroup\relationships\UserUserGroup;
use PKP\userGroup\UserGroup;
use stdClass;

class AboutContextHandler extends Handler
{
    /** @var string Max lifetime for the Editorial History users cache. */
    public const MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME = '1 year';

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

    /**
     * Clear editorial masthead cache
     */
    public static function forgetEditorialMastheadCache(int $contextId)
    {
        Cache::forget('PKP\pages\about\AboutContextHandler::editorialMasthead' . $contextId . AboutContextHandler::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME);
    }

    /**
     * Clear editorial history cache
     */
    public static function forgetEditorialHistoryCache(int $contextId)
    {
        Cache::forget('PKP\pages\about\AboutContextHandler::editorialMasthead' . $contextId . AboutContextHandler::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME);
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

        $savedMastheadUserGroupIdsOrder = (array) $context->getData('mastheadUserGroupIds');

        $collector = Repo::userGroup()->getCollector();
        $allMastheadUserGroups = $collector
            ->filterByContextIds([$context->getId()])
            ->filterByMasthead(true)
            ->orderBy($collector::ORDERBY_ROLE_ID)
            ->getMany()
            ->toArray();

        // sort the masthead roles in their saved order for display
        $mastheadRoles = array_replace(array_flip($savedMastheadUserGroupIdsOrder), $allMastheadUserGroups);

        // Cache/get cached array of user IDs groped by role IDs [user_group_id => [user_ids]]
        $key = __METHOD__ . $context->getId() . self::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME;
        $expiration = DateInterval::createFromDateString(static::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME);
        $allUsersIdsGroupedByUserGroupId = Cache::remember($key, $expiration, function () use ($mastheadRoles) {
            $mastheadRolesIds = array_map(
                function (UserGroup $item) {
                    return $item->getId();
                },
                $mastheadRoles
            );
            // Query that gets all users that are active in the given masthead roles
            // and that have accepted to be displayed on the masthead for the roles.
            // No need to filter by context ID, because the user groups are already filtered so.
            // Sort the results by role IDs and family name.
            $usersCollector = Repo::user()->getCollector();
            $usersQuery = $usersCollector
                ->filterByUserGroupIds($mastheadRolesIds)
                ->filterByUserUserGroupMastheadStatus(UserUserGroupMastheadStatus::STATUS_ON)
                ->orderBy($usersCollector::ORDERBY_FAMILYNAME, $usersCollector::ORDER_DIR_ASC, [Locale::getLocale(), Application::get()->getRequest()->getSite()->getPrimaryLocale()])
                ->orderByUserGroupIds($mastheadRolesIds)
                ->getQueryBuilder()
                ->get();

            // Get unique user IDs grouped by user group ID
            $userIdsByUserGroupId = $usersQuery->mapToGroups(function (stdClass $item, int $key) {
                return [$item->user_group_id => $item->user_id];
            })->map(function ($item) {
                return collect($item)->unique();
            });
            return $userIdsByUserGroupId->toArray();
        });

        $mastheadUsers = [];
        foreach ($mastheadRoles as $mastheadUserGroup) {
            if ($mastheadUserGroup->getRoleId() == Role::ROLE_ID_REVIEWER) {
                continue;
            }
            foreach ($allUsersIdsGroupedByUserGroupId[$mastheadUserGroup->getId()] ?? [] as $userId) {
                $user = Repo::user()->get($userId);
                $userUserGroup = UserUserGroup::withUserId($user->getId())
                    ->withUserGroupId($mastheadUserGroup->getId())
                    ->withActive()
                    ->withMasthead()
                    ->first();
                if ($userUserGroup) {
                    $startDatetime = new DateTime($userUserGroup->dateStart);
                    $mastheadUsers[$mastheadUserGroup->getId()][$user->getId()] = [
                        'user' => $user,
                        'dateStart' => $startDatetime->format('Y'),
                    ];
                }
            }
        }

        $previousYear = date('Y') - 1;
        $reviewerIds = Repo::reviewAssignment()->getReviewerIdsByCompletedYear($context->getId(), $previousYear);
        $usersCollector = Repo::user()->getCollector();
        $reviewers = $usersCollector
            ->filterByUserIds($reviewerIds->toArray())
            ->orderBy($usersCollector::ORDERBY_FAMILYNAME, $usersCollector::ORDER_DIR_ASC, [Locale::getLocale(), Application::get()->getRequest()->getSite()->getPrimaryLocale()])
            ->getMany();

        Hook::call('AboutContextHandler::editorialMasthead', [$mastheadRoles, $mastheadUsers, $reviewers, $previousYear]);

        // To come after https://github.com/pkp/pkp-lib/issues/9771
        // $orcidIcon = OrcidManager::getIcon();
        $orcidIcon = '';

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $templateMgr->assign([
            'mastheadRoles' => $mastheadRoles,
            'mastheadUsers' => $mastheadUsers,
            'reviewers' => $reviewers,
            'previousYear' => $previousYear,
            'orcidIcon' => $orcidIcon
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

        $savedMastheadUserGroupIdsOrder = (array) $context->getData('mastheadUserGroupIds');

        $collector = Repo::userGroup()->getCollector();
        $allMastheadUserGroups = $collector
            ->filterByContextIds([$context->getId()])
            ->filterByMasthead(true)
            ->orderBy($collector::ORDERBY_ROLE_ID)
            ->getMany()
            ->toArray();

        // sort the masthead roles in their saved order for display
        $mastheadRoles = array_replace(array_flip($savedMastheadUserGroupIdsOrder), $allMastheadUserGroups);

        // Cache/get cached array of user IDs groped by role IDs [user_group_id => [user_ids]]
        $key = __METHOD__ . $context->getId() . self::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME;
        $expiration = DateInterval::createFromDateString(static::MAX_EDITORIAL_MASTHEAD_CACHE_LIFETIME);
        $allUsersIdsGroupedByUserGroupId = Cache::remember($key, $expiration, function () use ($mastheadRoles) {
            $mastheadRolesIds = array_map(
                function (UserGroup $item) {
                    return $item->getId();
                },
                $mastheadRoles
            );
            // Query that gets all users that were active and are not active any more in the masthead roles
            // and that have accepted to be displayed on the masthead for the roles.
            // No need to filter by context ID, because the user groups are already filtered so.
            // Sort the results by role IDs and family name.
            $usersCollector = Repo::user()->getCollector();
            $usersQuery = $usersCollector
                ->filterByUserGroupIds($mastheadRolesIds)
                ->filterByUserUserGroupStatus(UserUserGroupStatus::STATUS_ENDED)
                ->filterByUserUserGroupMastheadStatus(UserUserGroupMastheadStatus::STATUS_ON)
                ->orderBy($usersCollector::ORDERBY_FAMILYNAME, $usersCollector::ORDER_DIR_ASC, [Locale::getLocale(), Application::get()->getRequest()->getSite()->getPrimaryLocale()])
                ->orderByUserGroupIds($mastheadRolesIds)
                ->getQueryBuilder()
                ->get();

            // Group all user IDs grouped by user group ID
            $userIdsByUserGroupId = $usersQuery->mapToGroups(function (stdClass $item, int $key) {
                return [$item->user_group_id => $item->user_id];
            })->map(function ($item) {
                return collect($item)->unique();
            });
            return $userIdsByUserGroupId->toArray();
        });

        $mastheadUsers = [];
        foreach ($mastheadRoles as $mastheadUserGroup) {
            if ($mastheadUserGroup->getRoleId() == Role::ROLE_ID_REVIEWER) {
                continue;
            }
            foreach ($allUsersIdsGroupedByUserGroupId[$mastheadUserGroup->getId()] ?? [] as $userId) {
                $user = Repo::user()->get($userId);
                $userUserGroups = UserUserGroup::withUserId($user->getId())
                    ->withUserGroupId($mastheadUserGroup->getId())
                    ->withEnded()
                    ->withMasthead()
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
                    $mastheadUsers[$mastheadUserGroup->getId()][$user->getId()] = [
                        'user' => $user,
                        'services' => $services
                    ];
                }
            }
        }

        Hook::call('AboutContextHandler::editorialHistory', [$mastheadRoles, $mastheadUsers]);

        // To come after https://github.com/pkp/pkp-lib/issues/9771
        // $orcidIcon = OrcidManager::getIcon();
        $orcidIcon = '';

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $templateMgr->assign([
            'mastheadRoles' => $mastheadRoles,
            'mastheadUsers' => $mastheadUsers,
            'orcidIcon' => $orcidIcon
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
