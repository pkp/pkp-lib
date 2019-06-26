<?php
/**
 * @file classes/security/authorization/PublicationAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PublicationAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Base class to control access to a publication
 */

import('lib.pkp.classes.security.authorization.internal.ContextPolicy');
import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
import('lib.pkp.classes.security.authorization.internal.PublicationRequiredPolicy');
import('lib.pkp.classes.security.authorization.internal.PublicationIsSubmissionPolicy');

class PublicationAccessPolicy extends ContextPolicy {

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request parameters
	 * @param $roleAssignments array
	 * @param $publicationParameterName string the request parameter we
	 *  expect the submission id in.
	 */
	function __construct($request, $args, $roleAssignments, $publicationParameterName = 'publicationId') {
    parent::__construct($request);

    // Can the user access this submission? (parameter name: 'submissionId')
    $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

    // Does the publication exist?
    $this->addPolicy(new PublicationRequiredPolicy($request, $args));

    // Is the publication attached to the correct submission?
    $this->addPolicy(new PublicationIsSubmissionPolicy(__('api.publications.403.submissionsDidNotMatch')));
	}
}


