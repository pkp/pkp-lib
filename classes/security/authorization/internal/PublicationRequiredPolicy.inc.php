<?php
/**
 * @file classes/security/authorization/internal/PublicationRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid publication id.
 */

import('lib.pkp.classes.security.authorization.DataObjectRequiredPolicy');

class PublicationRequiredPolicy extends DataObjectRequiredPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 * @param $publicationParameterName string the request parameter we expect
	 *  the submission id in.
	 */
	function __construct($request, &$args, $publicationParameterName = 'publicationId', $operations = null) {
		parent::__construct($request, $args, $publicationParameterName, 'user.authorization.invalidPublication', $operations);

		$callOnDeny = array($request->getDispatcher(), 'handle404', array());
		$this->setAdvice(
			AUTHORIZATION_ADVICE_CALL_ON_DENY,
			$callOnDeny
		);
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see DataObjectRequiredPolicy::dataObjectEffect()
	 */
	function dataObjectEffect() {
		// Get the publication id.
		$publicationId = $this->getDataObjectId();
		if ($publicationId === false) return AUTHORIZATION_DENY;

		$publication = Services::get('publication')->get($publicationId);
		if (!is_a($publication, 'Publication')) return AUTHORIZATION_DENY;

		// Save the publication to the authorization context.
		$this->addAuthorizedContextObject(ASSOC_TYPE_PUBLICATION, $publication);
		return AUTHORIZATION_PERMIT;
	}
}


