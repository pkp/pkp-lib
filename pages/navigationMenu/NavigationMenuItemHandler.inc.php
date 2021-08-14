<?php

/**
 * @file pages/navigationMenu/NavigationMenuItemHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemHandler
 * @ingroup pages_navigationMenu
 *
 * @brief Handle requests for navigationMenuItem functions.
 */

use APP\core\Services;

use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\security\Role;

class NavigationMenuItemHandler extends Handler
{
    /** @var NavigationMenuItem The nmi to view */
    public static $nmi;

    //
    // Implement methods from Handler.
    //
    /**
     * @copydoc Handler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        return parent::authorize($request, $args, $roleAssignments);
    }

    //
    // Public handler methods.
    //
    /**
     * View NavigationMenuItem content preview page.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function preview($args, $request)
    {
        $path = array_shift($args);
        $context = $request->getContext();
        $contextId = \PKP\core\PKPApplication::CONTEXT_ID_NONE;
        if ($context) {
            $contextId = $context->getId();
        }

        // Ensure that if we're previewing, the current user is a manager or admin.
        $roles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
        if (count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $roles)) == 0) {
            fatalError('The current user is not permitted to preview.');
        }

        // Assign the template vars needed and display
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */

        $navigationMenuItem = $navigationMenuItemDao->newDataObject();
        $navigationMenuItem->setContent((array) $request->getUserVar('content'), null);
        $navigationMenuItem->setTitle((array) $request->getUserVar('title'), null);

        Services::get('navigationMenu')->transformNavMenuItemTitle($templateMgr, $navigationMenuItem);

        $templateMgr->assign('title', $navigationMenuItem->getLocalizedTitle());

        $vars = [];
        if ($context) {
            $vars = [
                '{$contactName}' => $context->getData('contactName'),
                '{$contactEmail}' => $context->getData('contactEmail'),
                '{$supportName}' => $context->getData('supportName'),
                '{$supportPhone}' => $context->getData('supportPhone'),
                '{$supportEmail}' => $context->getData('supportEmail'),
            ];
        }

        $templateMgr->assign('content', strtr($navigationMenuItem->getLocalizedContent(), $vars));

        $templateMgr->display('frontend/pages/navigationMenuItemViewContent.tpl');
    }

    /**
     * View NavigationMenuItem content page.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function view($args, $request)
    {
        $path = array_shift($args);
        $context = $request->getContext();
        $contextId = \PKP\core\PKPApplication::CONTEXT_ID_NONE;
        if ($context) {
            $contextId = $context->getId();
        }

        // Assign the template vars needed and display
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */

        $navigationMenuItem = $navigationMenuItemDao->getByPath($contextId, $path);

        if (isset(self::$nmi)) {
            $templateMgr->assign('title', self::$nmi->getLocalizedTitle());

            $vars = [];
            if ($context) {
                $vars = [
                    '{$contactName}' => $context->getData('contactName'),
                    '{$contactEmail}' => $context->getData('contactEmail'),
                    '{$supportName}' => $context->getData('supportName'),
                    '{$supportPhone}' => $context->getData('supportPhone'),
                    '{$supportEmail}' => $context->getData('supportEmail'),
                ];
            }
            $templateMgr->assign('content', strtr(self::$nmi->getLocalizedContent(), $vars));

            $templateMgr->display('frontend/pages/navigationMenuItemViewContent.tpl');
        } else {
            return false;
        }
    }

    /**
     * Handle index request (redirect to "view")
     *
     * @param array $args Arguments array.
     * @param PKPRequest $request Request object.
     */
    public function index($args, $request)
    {
        $request->redirect(null, null, 'view', $request->getRequestedOp());
    }

    /**
     * Set a $nmi to view.
     *
     * @param NavigationMenuItem $nmi
     */
    public static function setPage($nmi)
    {
        self::$nmi = $nmi;
    }
}
