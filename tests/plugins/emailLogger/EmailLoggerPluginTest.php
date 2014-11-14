<?php

/**
 * @file tests/plugins/emailLogger/EmailLoggerTest.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EmailLoggerTest
 * @ingroup tests_plugins_emailLogger
 *
 * @brief Tests for the EmailLoggerPlugin class.
 */

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.emailLogger.EmailLoggerPlugin');

class EmailLoggerPluginTest extends PKPTestCase {
	
	/**
	 * @covers EmailLoggerPlugin::exists
	 */
	public function testExists() {
		// Create an email log for the test.
		$emailLog = array();
		$emailLog[] = array(
			'dateSent' => time(),
			'notification_type' => NOTIFICATION_TYPE_REVIEWER_COMMENT,
			'from' => array(array('name' => 'Ramiro Vaca', 'email' => 'rvaca@mailinator.com')),
			'recipients' => array(array('name' => 'Daniel Barnes', 'email' => 'dbarnes@mailinator.com'),
				array('name' => 'Adela Gallego', 'email' => 'agallego@mailinator.com')),
			'subject' => 'An email subject',
			'body' => 'Some text in here.'
		);
	
		$emailLog[] = array(
			'dateSent' => time(),
                        'notification_type' => NOTIFICATION_TYPE_REVIEW_ASSIGNMENT,
                        'from' => array(array('name' => 'Ramiro Vaca', 'email' => 'rvaca@mailinator.com')),
                        'recipients' => array(array('name' => 'Adela Gallego', 'email' => 'agallego@mailinator.com')),
                        'subject' => 'An email subject',
                        'body' => 'Some text in here.'
		);

		// Add the email log into the system under test.
		$emailLogPlugin = $this->getMock('EmailLoggerPlugin', array('getSetting'));
		$emailLogPlugin->expects($this->any())
			    ->method('getSetting')
			    ->will($this->returnValue($emailLog));

		// Test cases.
		$this->assertTrue($emailLogPlugin->exists());
		$this->assertTrue($emailLogPlugin->exists(NOTIFICATION_TYPE_REVIEW_ASSIGNMENT));
		$this->assertFalse($emailLogPlugin->exists(NOTIFICATION_TYPE_SUCCESS));

		$this->assertTrue($emailLogPlugin->exists(NOTIFICATION_TYPE_REVIEW_ASSIGNMENT, 'agallego@mailinator.com'));
		$this->assertTrue($emailLogPlugin->exists(NOTIFICATION_TYPE_REVIEW_ASSIGNMENT, 'agallego@mailinator.com', 'Some text in here.'));
		$this->assertFalse($emailLogPlugin->exists(NOTIFICATION_TYPE_REVIEW_ASSIGNMENT, 'agallego@mailinator.com', 'Some wrong text in here'));

		$this->assertFalse($emailLogPlugin->exists(NOTIFICATION_TYPE_REVIEW_ASSIGNMENT, 'dbarnes@mailinator.com'));
		$this->assertFalse($emailLogPlugin->exists(NOTIFICATION_TYPE_REVIEW_ASSIGNMENT, 'dbarnes@mailinator.com', 'Some text in here.' ));

		$this->assertTrue($emailLogPlugin->exists(null, 'dbarnes@mailinator.com'));
		$this->assertTrue($emailLogPlugin->exists(null, 'dbarnes@mailinator.com', 'Some text in')); // Incomplete body text also matches.
		$this->assertFalse($emailLogPlugin->exists(null, 'jbrower@mailinator.com'));

		$this->assertTrue($emailLogPlugin->exists(null, null, 'Some text in here'));
		$this->assertFalse($emailLogPlugin->exists(null, null, 'Some wrong text in here'));
	}

	/**
	 * @covers EmailLoggerPlugin::existsByAssoc()
	 */
	public function testExistsByAssoc() {
		import('lib.pkp.classes.log.EmailLogEntry');
		$logEntry = new EmailLogEntry();
		$logEntry->setAssocType(ASSOC_TYPE_SUBMISSION);
		$logEntry->setAssocId(1);
		$logEntry->setRecipients('"Adela Gallego" <agallego@mailinator.com>');
		$logEntry->setFrom('"Ramiro Vaca" <rvaca@mailinator.com>');
		$logEntry->setBody('Anything here in body so I can test retrieving the log entry checking the body. This is the part that will match.');
		$logEntry2 = clone $logEntry;
		$logEntry2->setAssocId(2);
		$logEntry2->setRecipients('"Jennifer Brower" <jbrower@mailinator.com>');
		$logEntry2->setBody('Anything here in body so I can test retrieving the log entry checking the body.');
		$logEntry3 = clone $logEntry;
		$logEntry3->setBody($logEntry2->getBody());
		$logEntry4 = clone $logEntry;
		$logEntry4->setRecipients($logEntry2->getRecipients());
		$logEntry5 = clone $logEntry;
		$logEntry5->setEventType(1);

		$entries = array($logEntry, $logEntry2, $logEntry3, $logEntry4, $logEntry5);
		
		$emailLogPlugin = $this->getMock('EmailLoggerPlugin', array('getLogByAssoc'));
                $emailLogPlugin->expects($this->any())
                            ->method('getLogByAssoc')
                            ->will($this->returnValue($entries));

		$this->assertTrue($emailLogPlugin->existsByAssoc(ASSOC_TYPE_SUBMISSION, 1));
		$this->assertTrue($emailLogPlugin->existsByAssoc(ASSOC_TYPE_SUBMISSION, 1, 'agallego@mailinator.com'));
		$this->assertFalse($emailLogPlugin->existsByAssoc(ASSOC_TYPE_SUBMISSION, 1, 'nonexisting@mailinator.com', 1, 'This is the part that will match'));
		$this->assertTrue($emailLogPlugin->existsByAssoc(ASSOC_TYPE_SUBMISSION, 1, null, 1)); 
		// Non existing event type.
		$this->assertFalse($emailLogPlugin->existsByAssoc(ASSOC_TYPE_SUBMISSION, 1, null, 2));
		// Matches everything but don't use event type.
		$this->assertTrue($emailLogPlugin->existsByAssoc(ASSOC_TYPE_SUBMISSION, 1, 'agallego@mailinator.com', null, 'This is the part that will match'));
		// Matches everything except event type.
		$this->assertFalse($emailLogPlugin->existsByAssoc(ASSOC_TYPE_SUBMISSION, 1, 'agallego@mailinator.com', 2, 'This is the part that will match')); 
		// Matches everything except body.
		$this->assertFalse($emailLogPlugin->existsByAssoc(ASSOC_TYPE_SUBMISSION, 1, 'agallego@mailinator.com', 2, 'There is no email with a body like this')); 
		// All should match.
		$this->assertTrue($emailLogPlugin->existsByAssoc(ASSOC_TYPE_SUBMISSION, 1, 'agallego@mailinator.com', 1, 'This is the part that will match'));
	}
}
