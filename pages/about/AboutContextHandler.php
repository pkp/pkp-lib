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
use PKP\plugins\Hook;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\Role;
use PKP\userGroup\relationships\enums\UserUserGroupMastheadStatus;
use PKP\userGroup\relationships\UserUserGroup;

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

        $mastheadUsers = [];
        foreach ($mastheadRoles as $mastheadUserGroup) {
            // Get all users that are active in the given role
            // and that have accepted to be displayed on the masthead for that role.
            // No need to filter by context ID, because the user groups are already filtered so.
            $users = Repo::user()
                ->getCollector()
                ->filterByUserGroupIds([$mastheadUserGroup->getId()])
                ->filterByUserUserGroupMastheadStatus(UserUserGroupMastheadStatus::STATUS_ON)
                ->getMany();
            foreach ($users as $user) {
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
        $reviewers = Repo::user()
            ->getCollector()
            ->filterByUserIds($reviewerIds->toArray())
            ->getMany()
            ->all();

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
