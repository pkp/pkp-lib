<?php
/**
 * @file classes/security/authorization/internal/RepresentationUploadAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RepresentationUploadAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that checks whether a file can be uploaded to a representation.
 *   It checks whether the user is allowed to access the representation file stage,
 *   whether the representation exists, whether it matches the authorized submission,
 *   and whether it is not part of a published publication. This policy expects an
 *   authorized submission in the authorization context.
 */

import('lib.pkp.classes.security.authorization.DataObjectRequiredPolicy');

class RepresentationUploadAccessPolicy extends DataObjectRequiredPolicy {

	/** @var int */
	public $_representationId;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 * @param $representationId int
	 */
	function __construct($request, &$args, $representationId) {
		parent::__construct($request, $args, 'user.authorization.accessDenied');
		$this->_representationId = $representationId;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see DataObjectRequiredPolicy::dataObjectEffect()
	 */
	function dataObjectEffect() {
		AppLocale::requireComponents([LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION]);

		$assignedFileStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_FILE_STAGES);
		if (empty($assignedFileStages) || !in_array(SUBMISSION_FILE_PROOF, $assignedFileStages)) {
			return AUTHORIZATION_DENY;
		}

		if (empty($this->_representationId))  {
			$this->setAdvice(AUTHORIZATION_ADVICE_DENY_MESSAGE, 'user.authorization.representationNotFound');
			return AUTHORIZATION_DENY;
		}

		$representationDao = Application::get()->getRepresentationDAO();
		$representation = $representationDao->getById($this->_representationId);

		if (!$representation) {
			return AUTHORIZATION_DENY;
		}

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		if (!$submission) {
			$this->setAdvice(AUTHORIZATION_ADVICE_DENY_MESSAGE, 'user.authorization.invalidSubmission');
			return AUTHORIZATION_DENY;
		}

		$publication = Services::get('publication')->get($representation->getData('publicationId'));
		if (!$publication) {
			$this->setAdvice(AUTHORIZATION_ADVICE_DENY_MESSAGE, 'galley.publicationNotFound');
			return AUTHORIZATION_DENY;
		}

		// Publication and submission must match
		if ($publication->getData('submissionId') !== $submission->getId()) {
			$this->setAdvice(AUTHORIZATION_ADVICE_DENY_MESSAGE, 'user.authorization.invalidPublication');
			return AUTHORIZATION_DENY;
		}

		// Representations can not be modified on published publications
		if ($publication->getData('status') === STATUS_PUBLISHED) {
			$this->setAdvice(AUTHORIZATION_ADVICE_DENY_MESSAGE, 'galley.editPublishedDisabled');
			return AUTHORIZATION_DENY;
		}

		$this->addAuthorizedContextObject(ASSOC_TYPE_REPRESENTATION, $representation);

		return AUTHORIZATION_PERMIT;
	}
}


