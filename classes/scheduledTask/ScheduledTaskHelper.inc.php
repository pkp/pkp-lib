<?php

/**
 * @file classes/scheduledTask/ScheduledTaskHelper.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ScheduledTaskHelper
 * @ingroup scheduledTask
 *
 * @brief Helper class for common scheduled tasks operations.
 */


class ScheduledTaskHelper {

	/**
	 * Get the arguments for a task from the parsed XML.
	 * @param XMLNode
	 * @return array
	 */
	function getTaskArgs($task) {
		$args = array();
		$index = 0;

		while(($arg = $task->getChildByName('arg', $index)) != null) {
			array_push($args, $arg->getValue());
			$index++;
		}

		return $args;
	}

	/**
	 * Check if the specified task should be executed according to the specified
	 * frequency and its last run time.
	 * @param $className string
	 * @param $frequency XMLNode
	 * @return string
	 */
	function checkFrequency($className, $frequency) {
		$isValid = true;
		$taskDao =& DAORegistry::getDAO('ScheduledTaskDAO'); /* @var $taskDao ScheduledTaskDAO */
		$lastRunTime = $taskDao->getLastRunTime($className);

		// Check day of week
		$dayOfWeek = $frequency->getAttribute('dayofweek');
		if (isset($dayOfWeek)) {
			$isValid = ScheduledTaskHelper::_isInRange($dayOfWeek, (int)date('w'), $lastRunTime, 'day', strtotime('-1 week'));
		}

		if ($isValid) {
			// Check month
			$month = $frequency->getAttribute('month');
			if (isset($month)) {
				$isValid = ScheduledTaskHelper::_isInRange($month, (int)date('n'), $lastRunTime, 'month', strtotime('-1 year'));
			}
		}

		if ($isValid) {
			// Check day
			$day = $frequency->getAttribute('day');
			if (isset($day)) {
				$isValid = ScheduledTaskHelper::_isInRange($day, (int)date('j'), $lastRunTime, 'day', strtotime('-1 month'));
			}
		}

		if ($isValid) {
			// Check hour
			$hour = $frequency->getAttribute('hour');
			if (isset($hour)) {
				$isValid = ScheduledTaskHelper::_isInRange($hour, (int)date('G'), $lastRunTime, 'hour', strtotime('-1 day'));
			}
		}

		if ($isValid) {
			// Check minute
			$minute = $frequency->getAttribute('minute');
			if (isset($minute)) {
				$isValid = ScheduledTaskHelper::_isInRange($minute, (int)date('i'), $lastRunTime, 'min', strtotime('-1 hour'));
			}
		}

		return $isValid;
	}

	/**
	 * Check if a value is within the specified range.
	 * @param $rangeStr string the range (e.g., 0, 1-5, *, etc.)
	 * @param $currentValue int value to check if its in the range
	 * @param $lastTimestamp int the last time the task was executed
	 * @param $timeCompareStr string value to use in strtotime("-X $timeCompareStr")
	 * @param $cutoffTimestamp int value will be considered valid if older than this
	 * @return boolean
	 */
	function _isInRange($rangeStr, $currentValue, $lastTimestamp, $timeCompareStr, $cutoffTimestamp) {
		$isValid = false;
		$rangeArray = explode(',', $rangeStr);

		if ($cutoffTimestamp > $lastTimestamp) {
			// Execute immediately if the cutoff time period has past since the task was last run
			$isValid = true;
		}

		for ($i = 0, $count = count($rangeArray); !$isValid && ($i < $count); $i++) {
			if ($rangeArray[$i] == '*') {
				// Is wildcard
				$isValid = true;

			} if (is_numeric($rangeArray[$i])) {
				// Is just a value
				$isValid = ($currentValue == (int)$rangeArray[$i]);

			} else if (preg_match('/^(\d*)\-(\d*)$/', $rangeArray[$i], $matches)) {
				// Is a range
				$isValid = ScheduledTaskHelper::_isInNumericRange($currentValue, (int)$matches[1], (int)$matches[2]);

			} else if (preg_match('/^(.+)\/(\d+)$/', $rangeArray[$i], $matches)) {
				// Is a range with a skip factor
				$skipRangeStr = $matches[1];
				$skipFactor = (int)$matches[2];

				if ($skipRangeStr == '*') {
					$isValid = true;

				} else if (preg_match('/^(\d*)\-(\d*)$/', $skipRangeStr, $matches)) {
					$isValid = ScheduledTaskHelper::_isInNumericRange($currentValue, (int)$matches[1], (int)$matches[2]);
				}

				if ($isValid) {
					// Check against skip factor
					$isValid = (strtotime("-$skipFactor $timeCompareStr") > $lastTimestamp);
				}
			}
		}

		return $isValid;
	}

	/**
	 * Check if a numeric value is within the specified range.
	 * @param $value int
	 * @param $min int
	 * @param $max int
	 * @return boolean
	 */
	function _isInNumericRange($value, $min, $max) {
		return ($value >= $min && $value <= $max);
	}

}

?>
