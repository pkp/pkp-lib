<?php
/**
 * @file classes/controllers/list/submissions/ArchivedSubmissionListHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ArchivedSubmissionListHandler
 * @ingroup classes_controllers_list
 *
 * @brief A handler for viewing all submissions that are no longer going through
 * the workflow, including published and rejected submissions.
 */
import('lib.pkp.controllers.list.submissions.SubmissionListHandler');

class ArchivedSubmissionListHandler extends SubmissionListHandler {
	/**
	 * Component path
	 *
	 * Used to generate component URLs
	 */
	public $_componentPath = 'list.submissions.ArchivedSubmissionListHandler';

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
		$context = $request->getContext();

		$user = $request->getUser();
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

		$search = isset($args['searchPhrase']) ? $args['searchPhrase'] : null;

		if (isset($args['range'])) {
			if (isset($args['range']['count'])) {
				$this->_range->setCount((int) $args['range']['count']);
			}
			if (isset($args['range']['page'])) {
				$this->_range->setPage((int) $args['range']['page']);
			}
		}

		$submissions = $submissionDao->getByStatus(
			array(STATUS_DECLINED, STATUS_PUBLISHED),
			$isManager ? null : $user->getId(),
			$context->getId(),
			null,
			null,
			null,
			$this->_range,
			$search
		)->toArray();

		$items = array();
		foreach($submissions as $submission) {
			$items[] = $submission->toArray();
		}

		return $items;
	}
}
