<?php

/**
 * @file classes/task/PublishSubmissions.inc.php
 *
 * Copyright (c) 2013-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PublishSubmissions
 * @ingroup tasks
 *
 * @brief Class to published submissions scheduled for publication.
 */

import('lib.pkp.classes.scheduledTask.ScheduledTask');

class PublishSubmissions extends ScheduledTask {

	/**
	 * @copydoc ScheduledTask::getName()
	 */
	public function getName() {
		return __('admin.scheduledTask.publishSubmissions');
	}

	/**
	 * @copydoc ScheduledTask::executeActions()
	 */
	public function executeActions() {
		import('classes.submission.Submission'); // import constants

		$contextsIterator = Services::get('context')->getMany([
			'isEnabled' => true,
		]);
		foreach ($contextsIterator as $context) {
			$submissionsIterator = Services::get('submission')->getMany([
				'contextId' => $context->getID(),
				'status' => STATUS_SCHEDULED,
			]);
			foreach ($submissionsIterator as $submission) {
				$datePublished = $submission->getCurrentPublication()->getData('datePublished');
				if ($datePublished && strtotime($datePublished) <= strtotime(Core::getCurrentDate())) {
					Services::get('publication')->publish($submission->getCurrentPublication());
				}
			}
		}

		return true;
	}
}


