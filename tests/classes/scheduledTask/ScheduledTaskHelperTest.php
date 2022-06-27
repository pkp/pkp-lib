<?php

/**
 * @file tests/classes/scheduledTask/ScheduledTaskHelperTest.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ScheduledTaskHelperTest
 * @ingroup tests_classes_scheduledTask
 *
 * @see ScheduledTask
 *
 * @brief Tests for the ScheduledTask class.
 */

import('lib.pkp.tests.PKPTestCase');

use PKP\mail\Mail;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;

class ScheduledTaskHelperTest extends PKPTestCase
{
    /**
     * Test ScheduledTaskHelper::notifyExecutionResult() method
     * when the scheduled task result is false.
     *
     * @param string $taskId
     * @param string $taskName
     * @param string $message
     * @dataProvider notifyExecutionResultTestsDataProvider
     * @covers ScheduledTaskHelper::notifyExecutionResult
     */
    public function testNotifyExecutionResultError($taskId, $taskName, $message)
    {
        $taskResult = false;
        $expectedSubject = __(ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR);
        $this->_setReportErrorOnly('On');
        $expectedTestResult = null; // Will send email (it's null because we mocked Mail::send()).

        $helper = $this->_getHelper($expectedSubject, $message);

        // Exercise the system.
        $actualResult = $helper->notifyExecutionResult($taskId, $taskName, $taskResult, $message);
        $this->assertEquals($expectedTestResult, $actualResult);

        // Now set report error only to off and we should get the same result.
        $this->_setReportErrorOnly('Off');
        $actualResult = $helper->notifyExecutionResult($taskId, $taskName, $taskResult, $message);
        $this->assertEquals($expectedTestResult, $actualResult);
    }

    /**
     * Test ScheduledTaskHelper::notifyExecutionResult() method
     * when the scheduled task result is true.
     *
     * @param string $taskId
     * @param string $taskName
     * @param string $message
     * @dataProvider notifyExecutionResultTestsDataProvider
     * @covers ScheduledTaskHelper::notifyExecutionResult
     */
    public function testNotifyExecutionResultSuccess($taskId, $taskName, $message)
    {
        $taskResult = true;
        $expectedSubject = __(ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED);
        $this->_setReportErrorOnly('On');
        $expectedTestResult = false; // Will NOT send email.

        $helper = $this->_getHelper($expectedSubject, $message);

        // Exercise the system.
        $actualResult = $helper->notifyExecutionResult($taskId, $taskName, $taskResult, $message);
        $this->assertEquals($expectedTestResult, $actualResult);

        // Now change the report setting, so success emails will also be sent.
        $this->_setReportErrorOnly('Off');
        $expectedTestResult = null; // Will send email.
        $actualResult = $helper->notifyExecutionResult($taskId, $taskName, $taskResult, $message);
        $this->assertEquals($expectedTestResult, $actualResult);
    }

    /**
     * All notifyExecutionResult tests data provider.
     *
     * @return array
     */
    public function notifyExecutionResultTestsDataProvider()
    {
        return [['someTaskId', 'TaskName', 'Any message']];
    }


    //
    // Private helper methods.
    //
    /**
     * Get helper mock object to exercise the system.
     *
     * @param string $expectedSubject
     * @param string $message
     *
     * @return ScheduledTaskHelper
     */
    private function _getHelper($expectedSubject, $message)
    {
        $helperMock = $this->getMockBuilder(ScheduledTaskHelper::class)
            ->onlyMethods(['getMail', 'getMessage'])
            ->setConstructorArgs(['some@email.com', 'Contact name'])
            ->getMock();
        $helperMock->expects($this->any())
            ->method('getMessage')
            ->will($this->returnValue($message));

        // Helper will use the Mail::send() method. Mock it.
        $mailMock = $this->getMockBuilder(Mail::class)
            ->onlyMethods(['send', 'setBody', 'setSubject'])
            ->getMock();

        $mailMock->expects($this->any())
            ->method('send');

        $mailMock->expects($this->any())
            ->method('setBody')
            ->with($this->equalTo($message));

        $mailMock->expects($this->any())
            ->method('setSubject')
            ->with($this->stringContains($expectedSubject));

        // Inject mail dependency.
        $helperMock->expects($this->any())
            ->method('getMail')
            ->will($this->returnValue($mailMock));

        return $helperMock;
    }

    /**
     * Set the scheduled_task_report_error_only setting value.
     *
     * @param string $state 'On' or 'Off'
     */
    private function _setReportErrorOnly($state)
    {
        $configData = & Config::getData();
        $configData['general']['scheduled_tasks_report_error_only'] = $state;
    }
}
