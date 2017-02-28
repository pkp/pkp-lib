<?php
/**
 * @file classes/controllers/list/submissions/ActiveSubmissionListHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ActiveSubmissionListHandler
 * @ingroup classes_controllers_list
 *
 * @brief A handler for viewing all submissions actively going through the
 *  workflow, from new submissions up to the time when they are published.
 */
import('lib.pkp.controllers.list.submissions.SubmissionListHandler');

class ActiveSubmissionListHandler extends SubmissionListHandler {
	/**
	 * Component path
	 *
	 * Used to generate component URLs
	 */
	public $_componentPath = 'list.submissions.ActiveSubmissionListHandler';

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

		if (isset($args['range'])) {
			if (isset($args['range']['count'])) {
				$this->_range->setCount((int) $args['range']['count']);
			}
			if (isset($args['range']['page'])) {
				$this->_range->setPage((int) $args['range']['page']);
			}
		}

		$submissions = $submissionDao->getActiveSubmissions(
			$context->getId(),
			null,
			null,
			null,
			null,
			$this->_range,
			false,
			$search
		)->toArray();

		$items = array();
		foreach($submissions as $submission) {
			$items[] = $submission->toArray();
		}

		return $items;
	}
}
