<?php

/**
 * @file plugins/generic/webFeed/WebFeedPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WebFeedPlugin
 * @brief Web Feeds plugin class
 */

namespace APP\plugins\generic\webFeed;

use APP\core\Application;
use APP\notification\NotificationManager;
use PKP\core\JSONMessage;
use PKP\core\PKPPageRouter;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;

class WebFeedPlugin extends GenericPlugin
{
    /**
     * Get the display name of this plugin
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.webfeed.displayName');
    }

    /**
     * Get the description of this plugin
     */
    public function getDescription(): string
    {
        return __('plugins.generic.webfeed.description');
    }

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null): bool
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }
        if ($this->getEnabled($mainContextId)) {
            Hook::add('TemplateManager::display', [$this, 'callbackAddLinks']);
            PluginRegistry::register('blocks', new WebFeedBlockPlugin($this), $this->getPluginPath());
            PluginRegistry::register('gateways', new WebFeedGatewayPlugin($this), $this->getPluginPath());
        }
        return true;
    }

    /**
     * Get the name of the settings file to be installed on new context creation.
     */
    public function getContextSpecificPluginSettingsFile(): string
    {
        return $this->getPluginPath() . '/settings.xml';
    }

    /**
     * Add feed links to page <head> on select/all pages.
     */
    public function callbackAddLinks(string $hookName, array $args): bool
    {
        // Only page requests will be handled
        $request = Application::get()->getRequest();
        if (!($request->getRouter() instanceof PKPPageRouter)) {
            return false;
        }

        $templateManager = $args[0];
        $currentServer = $templateManager->getTemplateVars('currentServer');
        if (is_null($currentServer)) {
            return false;
        }

        $displayPage = $this->getSetting($currentServer->getId(), 'displayPage');

        // Define when the <link> elements should appear
        $contexts = $displayPage == 'homepage' ? 'frontend-index' : 'frontend';

        $templateManager->addHeader(
            'webFeedAtom+xml',
            '<link rel="alternate" type="application/atom+xml" href="' . $request->url(null, 'gateway', 'plugin', ['WebFeedGatewayPlugin', 'atom']) . '">',
            ['contexts' => $contexts]
        );
        $templateManager->addHeader(
            'webFeedRdf+xml',
            '<link rel="alternate" type="application/rdf+xml" href="' . $request->url(null, 'gateway', 'plugin', ['WebFeedGatewayPlugin', 'rss']) . '">',
            ['contexts' => $contexts]
        );
        $templateManager->addHeader(
            'webFeedRss+xml',
            '<link rel="alternate" type="application/rss+xml" href="' . $request->url(null, 'gateway', 'plugin', ['WebFeedGatewayPlugin', 'rss2']) . '">',
            ['contexts' => $contexts]
        );

        return false;
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb): array
    {
        $actions = parent::getActions($request, $verb);
        if (!$this->getEnabled()) {
            return $actions;
        }

        $router = $request->getRouter();
        $url = $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']);
        array_unshift($actions, new LinkAction('settings', new AjaxModal($url, $this->getDisplayName()), __('manager.plugins.settings')));
        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request): JSONMessage
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        $form = new WebFeedSettingsForm($this, $request->getContext()->getId());
        if (!$request->getUserVar('save')) {
            $form->initData();
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->execute();
        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification($request->getUser()->getId());
        return new JSONMessage(true);
    }
}
