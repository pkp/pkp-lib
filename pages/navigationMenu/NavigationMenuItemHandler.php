<?php

/**
 * @file pages/navigationMenu/NavigationMenuItemHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemHandler
 *
 * @ingroup pages_navigationMenu
 *
 * @brief Handle requests for navigationMenuItem functions.
 */

namespace PKP\pages\navigationMenu;

use APP\core\Application;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\navigationMenu\NavigationMenuItem;
use PKP\navigationMenu\NavigationMenuItemDAO;
use PKP\security\Role;

class NavigationMenuItemHandler extends Handler
{
    public function __construct(public ?NavigationMenuItem $nmi = null)
    {
    }

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
        $context = $request->getContext();
        // Ensure that if we're previewing, the current user is a manager or admin.
        $roles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $roles)) == 0) {
            throw new \Exception('The current user is not permitted to preview.');
        }

        // Assign the template vars needed and display
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */

        $navigationMenuItem = $navigationMenuItemDao->newDataObject();
        $navigationMenuItem->setContent((array) $request->getUserVar('content'), null);
        $navigationMenuItem->setTitle((array) $request->getUserVar('title'), null);

        app()->get('navigationMenu')->transformNavMenuItemTitle($templateMgr, $navigationMenuItem);

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

        $templateMgr->assign([
            'title' => $navigationMenuItem->getLocalizedTitle(),
            'content' => strtr($navigationMenuItem->getLocalizedContent(), $vars)
        ]);

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
        if (!isset($this->nmi)) {
            return false;
        }

        // Assign the template vars needed and display
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $vars = [];
        $context = $request->getContext();
        if ($context) {
            $vars = [
                '{$contactName}' => $context->getData('contactName'),
                '{$contactEmail}' => $context->getData('contactEmail'),
                '{$supportName}' => $context->getData('supportName'),
                '{$supportPhone}' => $context->getData('supportPhone'),
                '{$supportEmail}' => $context->getData('supportEmail'),
            ];
        }
        $templateMgr->assign([
            'title' => $this->nmi->getLocalizedTitle(),
            'content' => strtr($this->nmi->getLocalizedContent(), $vars)
        ]);

        $templateMgr->display('frontend/pages/navigationMenuItemViewContent.tpl');
    }

    /**
     * Handle index request (redirect to "view")
     *
     * @param array $args Arguments array.
     * @param PKPRequest $request Request object.
     */
    public function index($args, $request)
    {
        $request->redirect(null, null, 'view', $args);
    }
}
