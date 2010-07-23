<?php

/**
 * @file controllers/api/user/CitationApiHandler.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationApiHandler
 * @ingroup controllers_api_user
 *
 * @brief Class defining the headless API for backend citation manipulation.
 */

// import the base Handler
import('lib.pkp.classes.handler.PKPHandler');

class CitationApiHandler extends PKPHandler {
	/**
	 * Constructor.
	 */
	function CitationApiHandler() {
		parent::PKPHandler();
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @see PKPHandler::authorize()
	 */
	function authorize(&$request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PublicHandlerOperationPolicy');
		$this->addPolicy(new PublicHandlerOperationPolicy($request, 'checkAllCitations'));
		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public handler methods
	//
	/**
	 * Check (parse and lookup) all raw citations
	 *
	 * NB: This handler method is meant to be called internally only.
	 * It can be called several times in parallel which will improve
	 * citation checking performance.
	 *
	 * The 'citation_checking_max_processes' config parameter will
	 * limit the number of parallel processes that can be started.
	 *
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function checkAllCitations(&$args, &$request) {
		// This is potentially a long running request. So
		// give us unlimited execution time.
		ini_set('max_execution_time', 0);

		// Check whether we've reached the limit of allowed
		// parallel processes.
		$maxProcesses = (int)Config::getVar('general', 'citation_checking_max_processes');
		$siteSettingsDao =& DAORegistry::getDAO('SiteSettingsDAO');
		$currentProcesses = (int)$siteSettingsDao->getSetting('citation_checking_executing_processes');
		if ($currentProcesses >= $maxProcesses) return 'Reached max execution limit!';

		// Increment the process variable.
		$siteSettingsDao->updateSetting('citation_checking_executing_processes', $currentProcesses + 1, 'int');

		// Find the request context
		$router =& $request->getRouter();
		$context =& $router->getContext($request);
		assert(is_object($context));

		// Run until all citations have been checked.
		$citationDao =& DAORegistry::getDAO('CitationDAO');
		while ($citationDao->checkNextRawCitation($context->getId()));

		// Decrement the process variable.
		$currentProcesses = (int)$siteSettingsDao->getSetting('citation_checking_executing_processes');
		$siteSettingsDao->updateSetting('citation_checking_executing_processes', max($currentProcesses - 1, 0), 'int');

		// This request returns just a status message.
		return 'Done!';
	}
}
?>