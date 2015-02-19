<?php

/**
 * @file tests/functional/workflow/WorkflowBaseTestCase.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WorkflowBaseTestCase
 * @ingroup tests_functional_workflow
 *
 * @brief Base class for all workflow related tests.
 */

import('tests.data.ContentBaseTestCase');
import('classes.notification.Notification');

class WorkflowBaseTestCase extends ContentBaseTestCase {

	/** @var $emailLog EmailLoggerPlugin */
	protected $emailLog;


	/**
	 * @copydoc WebTestCase::getAffectedTables()
	 */
	protected function getAffectedTables() {
		return PKP_TEST_ENTIRE_DB;
	}

	/**
	 * @copydoc ContentBaseTestCase::setUp()
	 */
	public function setUp() {
		parent::setUp();
		$plugin = PluginRegistry::loadPlugin('generic', 'emailLogger');
		if (!$plugin) $this->markTestSkipped('This test needs the Email Logger Plugin installed.');
		$this->emailLog = $plugin;

		$plugin->setEnabled(true);	
	}

	/**
	 * @copydoc ContentBaseTestCase::tearDown()
	 */
	public function tearDown() {
		parent::tearDown();

		$plugin = PluginRegistry::getPlugin('generic', 'emailloggerplugin');
		$plugin->setEnabled(false);
	}
}
