<?php

/**
 * @file classes/task/PublishSubmissions.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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

		$contextIds = Services::get('context')->getIds([
			'isEnabled' => true,
		]);
		foreach ($contextIds as $contextId) {
			$submissionsIterator = Services::get('submission')->getMany([
				'contextId' => $contextId,
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


