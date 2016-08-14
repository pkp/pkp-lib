<?php

/**
 * @file controllers/grid/eventLog/SubmissionEventLogGridHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionEventLogGridHandler
 * @ingroup controllers_grid_eventLog
 *
 * @brief Grid handler presenting the submission event log grid.
 */

// import grid base classes
import('lib.pkp.classes.controllers.grid.GridHandler');

// Link action & modal classes
import('lib.pkp.classes.linkAction.request.AjaxModal');

// Other classes used by this grid
import('lib.pkp.controllers.grid.eventLog.EventLogGridRow');
import('lib.pkp.classes.controllers.grid.DateGridCellProvider');
import('lib.pkp.controllers.grid.eventLog.EventLogGridCellProvider');

class SubmissionEventLogGridHandler extends GridHandler {
	/** @var Submission */
	var $_submission;

	/**
	 * Constructor
	 */
	function SubmissionEventLogGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR),
			array('fetchGrid', 'fetchRow', 'viewEmail')
		);
	}


	//
	// Getters/Setters
	//
	/**
	 * Get the submission associated with this grid.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Set the Submission
	 * @param $submission Submission
	 */
	function setSubmission($submission) {
		$this->_submission = $submission;
	}


	//
	// Overridden methods from PKPHandler
	//
	/**
	 * @see PKPHandler::authorize()
	 * @param $request PKPRequest
	 * @param $args array
	 * @param $roleAssignments array
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('classes.security.authorization.SubmissionAccessPolicy');
		$this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);

		// Retrieve the authorized monograph.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$this->setSubmission($submission);

		// Load submission-specific translations
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_PKP_SUBMISSION
		);

		// Columns
		$cellProvider = new EventLogGridCellProvider();
		$this->addColumn(
			new GridColumn(
				'date',
				'common.date',
				null,
				'controllers/grid/gridCell.tpl',
				new DateGridCellProvider(
					$cellProvider,
					Config::getVar('general', 'date_format_short')
				)
			)
		);
		$this->addColumn(
			new GridColumn(
				'user',
				'common.user',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider
			)
		);
		$this->addColumn(
			new GridColumn(
				'event',
				'common.event',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider,
				array('width' => 60)
			)
		);
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * @see GridHandler::getRowInstance()
	 * @return EventLogGridRow
	 */
	function getRowInstance() {
		return new EventLogGridRow($this->getSubmission());
	}

	/**
	 * Get the arguments that will identify the data in the grid
	 * In this case, the monograph.
	 * @return array
	 */
	function getRequestArgs() {
		$submission = $this->getSubmission();

		return array(
			'submissionId' => $submission->getId(),
		);
	}

	/**
	 * @copydoc GridHandler::loadData
	 */
	function loadData($request, $filter = null) {
		$submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO');
		$submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');

		$submission = $this->getSubmission();

		$eventLogEntries = $submissionEventLogDao->getBySubmissionId($submission->getId());
		$emailLogEntries = $submissionEmailLogDao->getBySubmissionId($submission->getId());

		$entries = array_merge($eventLogEntries->toArray(), $emailLogEntries->toArray());

		// Sort the merged data by date
		usort($entries, function($a, $b) {
			$aDate = is_a($a, 'EventLogEntry') ? $a->getDateLogged() : $a->getDateSent();
			$bDate = is_a($b, 'EventLogEntry') ? $b->getDateLogged() : $b->getDateSent();

			if ($aDate == $bDate) return 0;

			return $aDate > $bDate ? 1 : -1;
		});

		return $entries;
	}

	/**
	 * Get the contents of the email
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function viewEmail($args, $request) {
		$submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
		$emailLogEntry = $submissionEmailLogDao->getById((int) $args['emailLogEntryId']);
		$json = new JSONMessage(true, $this->_formatEmail($emailLogEntry));
		return $json->getString();
	}

	/**
	 * Format the contents of the email
	 * @param $emailLogEntry EmailLogEntry
	 * @return string Formatted email
	 */
	function _formatEmail($emailLogEntry) {
		assert(is_a($emailLogEntry, 'EmailLogEntry'));

		$text = array();
		$text[] = __('email.from') . ': ' . $emailLogEntry->getFrom();
		$text[] =  __('email.to') . ': ' . $emailLogEntry->getRecipients();
		$text[] =  __('email.subject') . ': ' . $emailLogEntry->getSubject();
		$text[] = $emailLogEntry->getBody();

		return nl2br(htmlentities(implode(PHP_EOL . PHP_EOL, $text)));
	}
}

?>
