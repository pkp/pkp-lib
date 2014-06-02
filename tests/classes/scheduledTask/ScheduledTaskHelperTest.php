<?php

/**
 * @file tests/classes/scheduledTask/ScheduledTaskHelperTest.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ScheduledTaskHelperTest
 * @ingroup tests_classes_scheduledTask
 * @see ScheduledTask
 *
 * @brief Tests for the ScheduledTask class.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.scheduledTask.ScheduledTask');

class ScheduledTaskHelperTest extends PKPTestCase {

	/**
	 * Test ScheduledTaskHelper::notifyExecutionResult() method.
	 * @dataProvider notifyExecutionResultDataProvider
	 * @covers notifyExecutionResult
	 */
	function testNotifyExecutionResult($result, $message) {
		$helperMock = $this->getMock('ScheduledTaskHelper', array('getMail'), array('some@email.com', 'Contact name'));

		// Helper will use the Mail::send() method. Mock it.
		import('lib.pkp.classes.mail.Mail');
		$mailMock = $this->getMock('Mail', array('send', 'setBody', 'setSubject'));

		// Expects to be called once.
		$mailMock->expects($this->once())
				 ->method('send');

		// Define what we expect for body.
		if ($message) {
			$constraint = 'equalTo';
			$params = array($message);
		} else {
			$constraint = 'anything';
			$params = array();
		}
		$mailMock->expects($this->once())
				 ->method('setBody')
				 ->with(call_user_func_array(array($this, $constraint), $params));

		// Define what we expect for subject.
		if ($result) {
			$expectedString = __(SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED);
		} else {
			$expectedString = __(SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
		}

		$mailMock->expects($this->once())
				 ->method('setSubject')
				 ->with($this->stringContains($expectedString));

		// Inject mail dependency.
		$helperMock->expects($this->any())
				   ->method('getMail')
				   ->will($this->returnValue($mailMock));


		// Exercise the system. Expect null because it will return the Mail::send() method
		// return value, which is null in the case of the mailMock object.
		$taskId = 'someTaskId';
		$taskName = 'TaskName';
		$this->assertNull($helperMock->notifyExecutionResult($taskId, $taskName, $result, $message));
	}

	/**
	 * testNotifyExecutionResult data provider.
	 */
	function notifyExecutionResultDataProvider() {
		$cases = array();
		$cases[0] = array(true, '');
		$cases[1] = array(true, 'A test message');
		$cases[2] = array(false, '');
		$cases[3] = array(false, 'A test message');

		return $cases;
	}
}
?>
