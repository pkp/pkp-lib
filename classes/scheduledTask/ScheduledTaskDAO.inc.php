<?php

/**
 * @defgroup scheduledTask Scheduled Tasks
 * Implements a scheduled task mechanism allowing for the periodic execution
 * of maintenance tasks, notification, etc.
 */

/**
 * @file classes/scheduledTask/ScheduledTaskDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ScheduledTaskDAO
 * @ingroup scheduledTask
 * @see ScheduledTask
 *
 * @brief Operations for retrieving and modifying Scheduled Task data.
 */


import('lib.pkp.classes.scheduledTask.ScheduledTask');

class ScheduledTaskDAO extends DAO {

	/**
	 * Get the last time a scheduled task was executed.
	 * @param $className string
	 * @return int
	 */
	function getLastRunTime($className) {
		$result = $this->retrieve(
			'SELECT last_run FROM scheduled_tasks WHERE class_name = ?',
			[$className]
		);
		$row = $result->current();
		return $row ? strtotime($this->datetimeFromDB($row->last_run)) : null;
	}

	/**
	 * Update a scheduled task's last run time.
	 * @param $className string
	 * @param $timestamp int optional, if omitted the current time is used.
	 * @return int
	 */
	function updateLastRunTime($className, $timestamp = null) {
		$result = $this->retrieve('SELECT COUNT(*) AS row_count FROM scheduled_tasks WHERE class_name = ?', [$className]);

		$row = $result->current();
		if ($row && $row->row_count != 0) {
			if (isset($timestamp)) return $this->update('UPDATE scheduled_tasks SET last_run = ' . $this->datetimeToDB($timestamp) . ' WHERE class_name = ?', [$className]);
			return $this->update( 'UPDATE scheduled_tasks SET last_run = NOW() WHERE class_name = ?', [$className]);
		} else {
			if (isset($timestamp)) return $this->update(
				sprintf('INSERT INTO scheduled_tasks (class_name, last_run) VALUES (?, %s)', $this->datetimeToDB($timestamp)),
				[$className]
			);
			return $this->update('INSERT INTO scheduled_tasks (class_name, last_run) VALUES (?, NOW())', [$className]);
		}
	}
}

