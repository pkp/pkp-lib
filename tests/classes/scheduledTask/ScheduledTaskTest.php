<?php

/**
 * @file tests/classes/scheduledTask/ScheduledTaskTest.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ScheduledTaskTest
 * @ingroup tests_classes_scheduledTask
 * @see ScheduledTask
 *
 * @brief Tests for the ScheduledTask class.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.scheduledTask.ScheduledTask');

class ScheduledTaskTest extends PKPTestCase {

	/**
	 * Test ScheduledTask::execute() method.
	 * @covers execute
	 */
	function testExecute() {

		// Need to create an ScheduledTask double object
		// to mock the abstract executeActions method and
		// to inject the ScheduledTaskHelper dependency.
		$scheduledTaskMock = $this->getMock('ScheduledTask', array('executeActions', 'getHelper'));

		// Test without notifying admin about the execution.
		// Expect null because the abstract method
		$this->assertNull($scheduledTaskMock->execute(false));

		// Test notifying admin.
		// Create ScheduledTaskHelper stub.
		$helperMock = $this->getMock('ScheduledTaskHelper', array(), array('some@email.com', 'Contact name'));

		// When executing task notifying the admin,
		// it should get the helper.
		$scheduledTaskMock->expects($this->once())
		                    ->method('getHelper')
		                    ->will($this->returnValue($helperMock));

		$this->assertNull($scheduledTaskMock->execute(true));
	}
}
?>
