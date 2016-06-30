<?php

/**
 * @file lib/pkp/controllers/page/PageHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PageHandler
 * @ingroup controllers_page
 *
 * @brief Handler for requests for page components such as the header, tasks,
 *  usernav, and CSS.
 */

import('classes.handler.Handler');

class PageHandler extends Handler {
	/**
	 * Constructor
	 */
	function PageHandler() {
		parent::Handler();
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PKPSiteAccessPolicy');
		$this->addPolicy(new PKPSiteAccessPolicy(
			$request,
			array('userNav', 'userNavBackend', 'tasks', 'css'),
			SITE_ACCESS_ALL_ROLES
		));
		if (!Config::getVar('general', 'installed')) define('SESSION_DISABLE_INIT', true);
		return parent::authorize($request, $args, $roleAssignments, false);
	}


	//
	// Public operations
	//
	/**
	 * Display the backend user-context menu.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function userNavBackend($args, $request) {
		$this->setupTemplate($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER); // Management menu items
		$templateMgr = TemplateManager::getManager($request);

		$this->setupHeader($args, $request);

		return $templateMgr->fetchJson('controllers/page/usernav.tpl');
	}

	/**
	 * Display the tasks component
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function tasks($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		return $templateMgr->fetchJson('controllers/page/tasks.tpl');
	}

	/**
	 * Get the compiled CSS
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function css($args, $request) {
		header('Content-Type: text/css');

		$stylesheetName = $request->getUserVar('name');
		switch ($stylesheetName) {
			case '':
			case null:
				$cacheDirectory = CacheManager::getFileCachePath();
				$compiledStylesheetFile = $cacheDirectory . '/compiled.css';
				if (!file_exists($compiledStylesheetFile)) {
					// Generate the stylesheet file
					require_once('lib/pkp/lib/vendor/oyejorge/less.php/lessc.inc.php');
					$less = new Less_Parser(array(
						'relativeUrls' => false,
						'compress' => true,
					));
					$less->parseFile('styles/index.less');
					$compiledStyles = str_replace('{$baseUrl}', $request->getBaseUrl(true), $less->getCss());

					// Allow plugins to intervene in stylesheet compilation
					HookRegistry::call('PageHandler::compileCss', array($request, $less, &$compiledStylesheetFile, &$compiledStyles));

					if (file_put_contents($compiledStylesheetFile, $compiledStyles) === false) {
						// If the stylesheet cache can't be written, log the error and
						// output the compiled styles directly without caching.
						error_log("Unable to write \"$compiledStylesheetFile\".");
						echo $compiledStyles;
						return;
					}
				}

				// Allow plugins to intervene in stylesheet display
				HookRegistry::call('PageHandler::displayCoreCss', array($request, &$compiledStylesheetFile));

				// Display the styles
				header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($compiledStylesheetFile)).' GMT');
				header('Content-Length: ' . filesize($compiledStylesheetFile));
				readfile($compiledStylesheetFile);
				break;
			default:
				// Allow plugins to intervene
				$result = null;
				$lastModified = null;
				TemplateManager::getManager($request); // Trigger loading of the themes plugins
				if (!HookRegistry::call('PageHandler::displayCss', array($request, &$stylesheetName, &$result, &$lastModified))) {
					if ($lastModified) header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
					header('Content-Length: ' . strlen($result));
					echo $result;
				}
		}
	}
	/**
	 * Setup and assign variables for any templates that want the overall header
	 * context.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	private function setupHeader($args, $request) {

		$templateMgr = TemplateManager::getManager($request);

		$workingContexts = $this->getWorkingContexts($request);
		$context = $request->getContext();

		if ($workingContexts && $workingContexts->getCount() > 1) {
			$dispatcher = $request->getDispatcher();
			$contextsNameAndUrl = array();
			while ($workingContext = $workingContexts->next()) {
				$contextUrl = $dispatcher->url($request, ROUTE_PAGE, $workingContext->getPath(), 'submissions');
				$contextsNameAndUrl[$contextUrl] = $workingContext->getLocalizedName();
			}

			// Get the current context switcher value. We don´t need to worry about the
			// value when there is no current context, because then the switcher will not
			// be visible.
			$currentContextUrl = null;
			$currentContextName = null;
			if ($context) {
				$currentContextUrl = $dispatcher->url($request, ROUTE_PAGE, $context->getPath());
				$currentContextName = $context->getLocalizedName();
			}

			$templateMgr->assign(array(
				'currentContextUrl' => $currentContextUrl,
				'currentContextName' => $currentContextName,
				'contextsNameAndUrl' => $contextsNameAndUrl,
				'multipleContexts' => true
			));
		} else {
			$templateMgr->assign('noContextsConfigured', true);
			if (!$workingContexts) {
				$templateMgr->assign('notInstalled', true);
			}
		}
	}
}

?>
