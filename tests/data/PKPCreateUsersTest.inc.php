<?php

/**
 * @file tests/data/PKPCreateUsersTest.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPCreateUsersTest
 * @ingroup tests_data
 *
 * @brief Data build suite: Create test users
 */

import('lib.pkp.tests.WebTestCase');

use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\WebDriverBy;

abstract class PKPCreateUsersTest extends WebTestCase {
	/**
	 * Creat user accounts.
	 * @param $users array
	 */
	protected function createUsers($users) {
		$this->open(self::$baseUrl);
		$actions = new WebDriverActions(self::$driver);
		$actions->moveToElement($this->waitForElementPresent('css=ul#navigationUser>li.profile>a'))
			->perform();
		$actions = new WebDriverActions(self::$driver);
		$actions->click($this->waitForElementPresent('//ul[@id="navigationUser"]//a[contains(text(),"Dashboard")]'))
			->perform();
		$actions = new WebDriverActions(self::$driver);
		$actions->moveToElement($this->waitForElementPresent('//ul[@id="navigationPrimary"]//a[text()="Users & Roles"]'))
			->perform();
		$actions = new WebDriverActions(self::$driver);
		$actions->click($this->waitForElementPresent('//ul[@id="navigationPrimary"]//a[text()="Users"]'))
			->perform();

		foreach ($users as $data) {
			// Come up with sensible defaults for data not supplied
			$username = $data['username'];
			$data = array_merge(array(
				'email' => $username . '@mailinator.com',
				'password' => $username . $username,
				'password2' => $username . $username,
				'roles' => array()
			), $data);

			$this->waitForElementPresent($selector='css=[id^=component-grid-settings-user-usergrid-addUser-button-]');
			$this->click($selector);
			$this->waitForElementPresent('css=[id^=givenName-]');

			// Fill in user data
			$this->type('css=[id^=givenName-]', $data['givenName']);
			$this->type('css=[id^=familyName-]', $data['familyName']);
			$this->type('css=[id^=username-]', $username);
			$this->type('css=[id^=email-]', $data['email']);
			$this->type('css=[id^=password-]', $data['password']);
			$this->type('css=[id^=password2-]', $data['password2']);
			if (isset($data['country'])) $this->select('id=country', 'label=' . $data['country']);

			$this->click('//a[@class="toggleExtras"]');
			if (isset($data['affiliation'])) $this->type('css=[id^=affiliation-]', $data['affiliation']);
			$this->click('css=[id=mustChangePassword]'); // Uncheck the reset password requirement
			$this->click('//button[text()=\'OK\']');

			// Roles
			$this->waitForElementPresent('css=input[name^=userGroupIds]');
			foreach ($data['roles'] as $role) {
				$this->click('//label[normalize-space(text())=\'' . $role . '\']');
			}

			$this->click('//button[text()=\'Save\']');
			self::$driver->wait()->until(WebDriverExpectedCondition::invisibilityOfElementLocated(WebDriverBy::cssSelector('div.pkp_modal_panel')));
		}
	}
}
