<?php
/**
 * @defgroup controllers_api_citation
 */

/**
 * @file controllers/api/user/CitationApiHandler.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
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
	function authorize(&$request, $args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PKPProcessAccessPolicy');
		$this->addPolicy(new PKPProcessAccessPolicy($request, $args, 'checkAllCitations'));
		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public handler methods
	//
	/**
	 * Check (parse and lookup) all raw citations
	 *
	 * NB: This handler method is meant to be called by the parallel
	 * processing framework (see ProcessDAO::spawnProcesses()). Executing
	 * this handler in parallel will significantly improve citation
	 * checking performance.
	 *
	 * The 'citation_checking_max_processes' config parameter limits
	 * the number of parallel processes that can be started in parallel.
	 *
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function checkAllCitations($args, &$request) {
		// This is potentially a long running request. So
		// give us unlimited execution time.
		ini_set('max_execution_time', 0);

		// Get the process id.
		$processId = $args['authToken'];

		// Run until all citations have been checked.
		$processDao =& DAORegistry::getDAO('ProcessDAO');
		$citationDao =& DAORegistry::getDAO('CitationDAO');
		do {
			// Check that the process lease has not expired.
			$continue = $processDao->canContinue($processId);

			if ($continue) {
				// Check the next citation.
				$continue = $citationDao->checkNextRawCitation($request, $processId);
			}
		} while ($continue);

		// Free the process slot.
		$processDao->deleteObjectById($processId);

		// This request returns just a (private) status message.
		return 'Done!';
	}
}
?>
