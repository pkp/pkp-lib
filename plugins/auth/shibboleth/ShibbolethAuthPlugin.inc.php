<?php

/**
 * @file plugins/auth/shibboleth/ShibbolethAuthPlugin.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ShibbolethAuthPlugin
 * @ingroup plugins_auth_shibboleth
 *
 * @brief Shibboleth authentication plugin.
 */

import('lib.pkp.classes.plugins.AuthPlugin');

class ShibbolethAuthPlugin extends AuthPlugin {
	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True iff plugin initialized successfully; if false,
	 * the plugin will not be registered.
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * Return the name of this plugin.
	 * @return string
	 */
	function getName() {
		return 'ShibbolethAuthPlugin';
	}

	/**
	 * Return the localized name of this plugin.
	 * @return string
	 */
	function getDisplayName() {
		return __('plugins.auth.shibboleth.displayName');
	}

	/**
	 * Return the localized description of this plugin.
	 * @return string
	 */
	function getDescription() {
		return __('plugins.auth.shibboleth.description');
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	function getActions($request, $verb) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled()?array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url(
							$request,
							null,
							null,
							'manage',
							null,
							array(
								'verb' => 'settings',
								'plugin' => $this->getName(),
								'category' => 'auth'
							)
						),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			):array(),
			parent::getActions($request, $verb)
		);
	}

 	/**
	 * @copydoc Plugin::manage()
	 */
	function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON,  LOCALE_COMPONENT_PKP_MANAGER);
				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->register_function('plugin_url', array($this, 'smartyPluginUrl'));

				$this->import('ShibbolethSettingsForm');
				$form = new ShibbolethSettingsForm($this, $this->getCurrentContextId());

				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	/**
	 * @copydoc Plugin::getTemplatePath
	 */
	function getTemplatePath($inCore = false) {
		return parent::getTemplatePath($inCore) . 'templates/';
	}


	//
	// Public methods required to support lazy load.
	// We don’t inherit from LazyLoadPlugin, but we need to able to be
	// enabled or disabled.
	//
	/**
	 * Determine whether or not this plugin is currently enabled.
	 * @return boolean
	 */
	function getEnabled() {
		return $this->getSetting($this->getCurrentContextId(), 'enabled');
	}

	/**
	 * Set whether or not this plugin is currently enabled.
	 * @param $enabled boolean
	 */
	function setEnabled($enabled) {
		$this->updateSetting($this->getCurrentContextId(), 'enabled', $enabled, 'bool');
	}

	/**
	 * Determine whether the plugin can be enabled.
	 * @return boolean
	 */
	function getCanEnable() {
		return true;
	}

	/**
	 * Determine whether the plugin can be disabled.
	 * @return boolean
	 */
	function getCanDisable() {
		return true;
	}


	//
	// Core Plugin Functions
	// (Must be implemented by every authentication plugin)
	//

	/**
	 * Returns an instance of the authentication plugin
	 * @param $settings array settings specific to this instance.
	 * @param $authId int identifier for this instance
	 * @return ShibbolethAuthPlugin
	 */
	function getInstance($settings, $authId) {
		return new ShibbolethAuthPlugin($settings, $authId);
	}

	/**
	 * Authenticate a username and password.
	 * @param $username string
	 * @param $password string
	 * @return boolean true if authentication is successful
	 */
	function authenticate($username, $password) {
		return false;
	}

	/**
	 * Get the current context ID or the site-wide context ID (0) if no context
	 * can be found.
	 */
	function getCurrentContextId() {
		$context = PKPApplication::getRequest()->getContext();
		return is_null($context) ? 0 : $context->getId();
	}
}

?>
