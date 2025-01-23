<?php

/**
 * @file pages/orcid/OrcidHandler.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidHandler
 *
 * @ingroup pages_orcid
 *
 * @brief Handle requests for ORCID-related functions.
 */

namespace PKP\pages\orcid;

use APP\author\Author;
use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPSessionGuard;
use PKP\identity\Identity;
use PKP\orcid\actions\AuthorizeUserData;
use PKP\orcid\actions\VerifyAuthorWithOrcid;
use PKP\orcid\actions\VerifyIdentityWithOrcid;
use PKP\orcid\enums\OrcidDepositType;
use PKP\orcid\OrcidManager;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\authorization\UserRequiredPolicy;
use PKP\user\User;

class OrcidHandler extends Handler
{
    protected const VERIFY_TEMPLATE_PATH = 'frontend/pages/orcidVerify.tpl';
    protected const ABOUT_TEMPLATE_PATH = 'frontend/pages/orcidAbout.tpl';

    /** @inheritDoc */
    public function authorize($request, &$args, $roleAssignments)
    {
        // Authorize all requests
        $this->addPolicy(new PKPSiteAccessPolicy(
            $request,
            ['verify', 'authorizeOrcid', 'about', 'updateScope'],
            PKPSiteAccessPolicy::SITE_ACCESS_ALL_ROLES
        ));

        $op = $request->getRequestedOp();
        $targetOp = $request->getUserVar('targetOp');
        if ($op === 'authorize' && in_array($targetOp, ['profile', 'submit'])) {
            // ... but user must be logged in for authorize with profile or submit
            $this->addPolicy(new UserRequiredPolicy($request));
        }

        if (!Application::isInstalled()) {
            PKPSessionGuard::disableSession();
        }

        $this->setEnforceRestrictedSite(false);
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Completes ORCID OAuth process and displays ORCID verification landing page
     */
    public function verify(array $args, Request $request): void
    {
        // If the application is set to sandbox mode, it will not reach out to external services
        if (Config::getVar('general', 'sandbox', false)) {
            error_log('Application is set to sandbox mode and will not interact with the ORCID service');
            return;
        }

        $templateMgr = TemplateManager::getManager($request);

        // Initialise template parameters
        $templateMgr->assign([
            'currentUrl' => $request->url(null, 'index'),
            'verifySuccess' => false,
            'authFailure' => false,
            'notPublished' => false,
            'sendSubmission' => false,
            'sendSubmissionSuccess' => false,
            'denied' => false,
            'contextName' => $request->getContext()->getName($request->getContext()->getPrimaryLocale()),
        ]);

        // Get the author
        $author = $this->getAuthorToVerify($request);

        if ($author === null) {
            $this->handleNoAuthorWithToken($templateMgr);
        } elseif ($request->getUserVar('error') === 'access_denied') {
            // Handle access denied
            $this->handleUserDeniedAccess($author, $templateMgr, $request->getUserVar('error_description'));
        }

        (new VerifyIdentityWithOrcid($author, $request, OrcidDepositType::WORK))
            ->execute()
            ->updateTemplateMgrVars($templateMgr);

        $templateMgr->display(self::VERIFY_TEMPLATE_PATH);
    }

    /**
     * Directly authorizes ORCID via user-initiated action and populates page with authorized ORCID info.
     */
    public function authorizeOrcid(array $args, Request $request): void
    {
        // If the application is set to sandbox mode, it will not reach out to external services
        if (Config::getVar('general', 'sandbox', false)) {
            error_log('Application is set to sandbox mode and will not interact with the ORCID service');
            return;
        }

        (new AuthorizeUserData($request))->execute();
    }

    /**
     * Displays information about ORCID functionality on reader-facing frontend.
     */
    public function about(array $args, Request $request): void
    {
        $context = $request->getContext();
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign('orcidIcon', OrcidManager::getIcon());
        $templateMgr->assign('isMemberApi', OrcidManager::isMemberApiEnabled($context));
        $templateMgr->display(self::ABOUT_TEMPLATE_PATH);

    }

    /**
     * Displays page for completed OAuth process when the goal is to update the author's/user's OAuth scope and
     * resubmit the ORCID item for deposit requiring the updated scope.
     */
    public function updateScope(array $args, Request $request): void
    {
        // If the application is set to sandbox mode, it will not reach out to external services
        if (Config::getVar('general', 'sandbox', false)) {
            error_log('Application is set to sandbox mode and will not interact with the ORCID service');
            return;
        }

        $templateMgr = TemplateManager::getManager($request);

        // Initialise template parameters
        $templateMgr->assign([
            'currentUrl' => $request->url(null, 'index'),
            'verifySuccess' => false,
            'authFailure' => false,
            'notPublished' => false,
            'sendSubmission' => false,
            'sendSubmissionSuccess' => false,
            'denied' => false,
            'contextName' => $request->getContext()->getName($request->getContext()->getPrimaryLocale()),
        ]);

        $identity = $this->getIdentityToVerify($request);

        if ($identity === null) {
            $this->handleNoAuthorWithToken($templateMgr);
        } elseif ($request->getUserVar('error') === 'access_denied') {
            // Handle access denied
            $this->handleUserDeniedAccess($identity, $templateMgr, $request->getUserVar('error_description'));
        }

        $depositType = OrcidDepositType::tryFrom($request->getUserVar('itemType'));
        if ($depositType !== null) {
            (new VerifyIdentityWithOrcid($identity, $request, $depositType))
                ->execute()
                ->updateTemplateMgrVars($templateMgr);
        }

        $templateMgr->display(self::VERIFY_TEMPLATE_PATH);
    }

    /**
     * Helper to retrieve author for which the ORCID verification was requested.
     */
    private function getAuthorToVerify(Request $request): ?Author
    {
        $publicationId = $request->getUserVar('state');
        $authors = Repo::author()
            ->getCollector()
            ->filterByPublicationIds([$publicationId])
            ->getMany();

        $authorToVerify = null;
        // Find the author entry specified by the returned token.
        if ($request->getUserVar('token')) {
            foreach ($authors as $author) {
                if ($author->getData('orcidEmailToken') == $request->getUserVar('token')) {
                    $authorToVerify = $author;
                }
            }
        }

        return $authorToVerify;
    }

    /**
     * Log error and set failure variable in TemplateManager
     */
    private function handleNoAuthorWithToken(TemplateManager $templateMgr): void
    {
        OrcidManager::logError('OrcidHandler::verify = No author found with supplied token');
        $templateMgr->assign('verifySuccess', false);
    }

    /**
     * Remove previously assigned ORCID OAuth related fields and assign denied variable to TemplateManagerA
     */
    private function handleUserDeniedAccess(Identity $identity, TemplateManager $templateMgr, string $errorDescription): void
    {
        // User denied access
        // Store the date time the author denied ORCID access to remember this
        $identity->setData('orcidAccessDenied', Core::getCurrentDate());
        // remove all previously stored ORCID access token
        $identity->setData('orcidAccessToken', null);
        $identity->setData('orcidAccessScope', null);
        $identity->setData('orcidRefreshToken', null);
        $identity->setData('orcidAccessExpiresOn', null);
        $identity->setData('orcidEmailToken', null);

        if ($identity instanceof Author) {
            Repo::author()->dao->update($identity);
        } else if ($identity instanceof User) {
            Repo::user()->dao->update($identity);
        }
        OrcidManager::logError('OrcidHandler::verify - ORCID access denied. Error description: ' . $errorDescription);
        $templateMgr->assign('denied', true);
    }

    private function getIdentityToVerify(Request $request): ?Identity
    {
        return match (OrcidDepositType::tryFrom($request->getUserVar('itemType'))) {
            OrcidDepositType::WORK => $this->getAuthorToVerify($request),
            OrcidDepositType::REVIEW => $this->getReviewerToVerify($request),
            default => null,
        };
    }

    private function getReviewerToVerify(Request $request): ?User
    {
        $user = null;
        $userId = $request->getUserVar('userId');
        $user = Repo::user()->get($userId);
        if (
            $user === null ||
            empty($request->getUserVar('token')) ||
            $user->getData('orcidEmailToken') != $request->getUserVar('token')
        ) {
            return null;
        }

        return $user;
    }
}
