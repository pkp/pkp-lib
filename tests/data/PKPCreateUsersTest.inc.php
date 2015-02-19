<?php

/**
 * @file tests/data/PKPCreateUsersTest.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPCreateUsersTest
 * @ingroup tests_data
 *
 * @brief Data build suite: Create test users
 */

import('lib.pkp.tests.WebTestCase');

class PKPCreateUsersTest extends WebTestCase {
	/**
	 * Create a user account.
	 * @param $data array
	 */
	protected function createUser($data) {
		// Come up with sensible defaults for data not supplied
		$username = $data['username'];
		$data = array_merge(array(
			'email' => $username . '@mailinator.com',
			'password' => $username . $username,
			'password2' => $username . $username,
			'roles' => array()
		), $data);

		$this->open(self::$baseUrl);
		$this->waitForElementPresent('link=User Home');
		$this->clickAndWait('link=User Home');
		$this->waitForElementPresent('link=Journal Manager');
		$this->clickAndWait('link=Journal Manager');
		$this->waitForElementPresent('link=Create New User');
		$this->clickAndWait('link=Create New User');
		$this->waitForElementPresent('id=firstName');

		// Fill in user data
		$this->type('id=firstName', $data['firstName']);
		$this->type('id=lastName', $data['lastName']);
		$this->type('id=username', $username);
		$this->type('id=email', $data['email']);
		$this->type('id=password', $data['password']);
		$this->type('id=password2', $data['password2']);
		if (isset($data['country'])) $this->select('id=country', $data['country']);
		if (isset($data['affiliation'])) $this->type('id=affiliation', $data['affiliation']);

		// Roles
		$this->removeSelection('id=enrollAs', 'label=With no role');
		foreach ($data['roles'] as $role) {
			$this->addSelection('id=enrollAs', 'label=' . $role);
		}

		$this->clickAndWait('css=input.button.defaultButton');
	}
}
