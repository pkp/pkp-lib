<?php
/**
 * @file controllers/modals/submission/ViewSubmissionMetadataHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ViewSubmissionMetadataHandler
 * @ingroup controllers_modals_viewSubmissionMetadataHandler
 *
 * @brief Display submission metadata.
 */

// Import the base Handler.
import('classes.handler.Handler');

class ViewSubmissionMetadataHandler extends handler {

	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(array(ROLE_ID_REVIEWER), array('display'));
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
		$this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Display metadata
	 */
	function display($args, $request) {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
		$context = $request->getContext();
		$templateMgr = TemplateManager::getManager($request);
		$publication = $submission->getCurrentPublication();

		if ($reviewAssignment->getReviewMethod() != SUBMISSION_REVIEW_METHOD_DOUBLEBLIND) { /* SUBMISSION_REVIEW_METHOD_BLIND or _OPEN */
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
			$userGroups = $userGroupDao->getByContextId($context->getId())->toArray();
			$templateMgr->assign('authors', $publication->getAuthorString($userGroups));
		}

		$templateMgr->assign('publication', $publication);

		if ($publication->getLocalizedData('keywords')) {
			$additionalMetadata[] = array(__('common.keywords'), implode(', ', $publication->getLocalizedData('keywords')));
		}
		if ($publication->getLocalizedData('subjects')) {
			$additionalMetadata[] = array(__('common.subjects'), implode(', ', $publication->getLocalizedData('subjects')));			
		}
		if ($publication->getLocalizedData('disciplines')) {
			$additionalMetadata[] = array(__('common.discipline'), implode(', ', $publication->getLocalizedData('disciplines')));
		}
		if ($publication->getLocalizedData('agencies')) {
			$additionalMetadata[] = array(__('submission.agencies'), implode(', ', $publication->getLocalizedData('agencies')));
		}
		if ($publication->getLocalizedData('languages')) {
			$additionalMetadata[] = array(__('common.languages'), implode(', ', $publication->getLocalizedData('languages')));
		}		

		$templateMgr->assign('additionalMetadata', $additionalMetadata);

		return $templateMgr->fetchJson('controllers/modals/submission/viewSubmissionMetadata.tpl');

	}
}
