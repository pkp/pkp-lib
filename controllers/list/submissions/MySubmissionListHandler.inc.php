<?php
/**
 * @file classes/controllers/list/submissions/MySubmissionListHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MySubmissionListHandler
 * @ingroup classes_controllers_list
 *
 * @brief A handler for viewing submissions assigned to the current user. If the
 *  current user is a journal manager, it will also display unassigned
 *  submissions.
 */
import('lib.pkp.controllers.list.submissions.SubmissionListHandler');

class MySubmissionListHandler extends SubmissionListHandler {
	/**
	 * Component path
	 *
	 * Used to generate component URLs
	 */
	public $_componentPath = 'list.submissions.MySubmissionListHandler';

	/**
	 * Override the setRoutes method to allow more roles to access their
	 * assigned submissions.
	 *
	 * @return array Routes supported by this component
	 */
	public function setRoutes() {

		parent::setRoutes();

		$this->modifyRoute('get', array(
			'roleAccess' => array(
				ROLE_ID_SITE_ADMIN,
				ROLE_ID_MANAGER,
				ROLE_ID_SUB_EDITOR,
				ROLE_ID_AUTHOR,
				ROLE_ID_REVIEWER,
				ROLE_ID_ASSISTANT,
			)
		));

		return $this->_routes;
	}

	/**
	 * Helper function to retrieve all items assigned to the author
	 *
	 * @param array $args None supported at this time
	 * @return array Items requested
	 */
	public function getItems($args = array()) {

		import('classes.article.ArticleDAO');

		$submissionDao = Application::getSubmissionDAO();
		$request = Application::getRequest();
		$user = $request->getUser();
		$context = $request->getContext();

		$search = isset($args['searchPhrase']) ? $args['searchPhrase'] : null;

		$assigned = $submissionDao->getAssignedToUser(
			$user->getId(),
			$context->getId(),
			null,
			null,
			null,
			null,
			null,
			$search
		)->toArray();

		import('classes.security.RoleDAO');
		$userRolesDao = DAORegistry::getDAO('RoleDAO');
		$userRoles = $userRolesDao->getByUserId($user->getId(), $context->getId());

		$isManager = false;
		foreach($userRoles as $role) {
			if ($role->getId() == ROLE_ID_MANAGER) {
				$isManager = true;
				break;
			}
		}

		$unassigned = array();
		if ($isManager) {
			$unassigned = $submissionDao->getBySubEditorId(
				$context->getId(),
				null,
				false, // do not include STATUS_DECLINED submissions
				false,  // include only unpublished submissions
				null,
				null,
				$search
			)->toArray();
		}

		$authored = $submissionDao->getUnpublishedByUserId(
			$user->getId(),
			$context->getId(),
			null,
			null,
			null,
			$search
		)->toArray();

		$submissions = array_merge($unassigned, $authored, $assigned);

		$items = array();
		foreach($submissions as $submission) {
			$items[] = $submission->toArray();
		}

		return array(
			'items' => $items,
			'maxItems' => null,
		);
	}
}
