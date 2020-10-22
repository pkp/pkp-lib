<?php
/**
 * @file classes/security/authorization/NoteAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NoteAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to a note
 *
 * NB: This policy expects previously authorized submission, query and
 * accessibile workflow stages in the authorization context.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

define('NOTE_ACCESS_READ', 1);
define('NOTE_ACCESS_WRITE', 2);

class NoteAccessPolicy extends AuthorizationPolicy {

	/** @var Request */
	private $_request;

	/** @var int */
	private $_noteId;

	/** @var int */
	private $_accessMode;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $noteId int
	 * @param $accessMode int NOTE_ACCESS_...
	 */
	function __construct($request, $noteId, $accessMode) {
		parent::__construct('user.authorization.accessDenied');
		$this->_request = $request;
		$this->_noteId = $noteId;
		$this->_accessMode = $accessMode;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {

		if (!$this->_noteId) {
			return AUTHORIZATION_DENY;
		}

		$query = $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$assignedStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

		if (!$query || !$submission || empty($assignedStages)) {
			return AUTHORIZATION_DENY;
		}

		$noteDao = DAORegistry::getDAO('NoteDAO'); /* @var $noteDao NoteDAO */
		$note = $noteDao->getById($this->_noteId);

		if (!is_a($note, 'Note')) {
			return AUTHORIZATION_DENY;
		}

		// Note, query, submission and assigned stages must match
		if ($note->getAssocId() != $query->getId()
				|| $note->getAssocType() != ASSOC_TYPE_QUERY
				|| $query->getAssocId() != $submission->getId()
				|| $query->getAssocType() != ASSOC_TYPE_SUBMISSION
				|| !array_key_exists($query->getStageId(), $assignedStages)
				|| empty($assignedStages[$query->getStageId()])) {
			return AUTHORIZATION_DENY;
		}

		// Notes can only be edited by their original creators
		if ($this->_accessMode === NOTE_ACCESS_WRITE
				&& $note->getUserId() != $this->_request->getUser()->getId()) {
			return AUTHORIZATION_DENY;
		}

		$this->addAuthorizedContextObject(ASSOC_TYPE_NOTE, $note);

		return AUTHORIZATION_PERMIT;
	}
}


