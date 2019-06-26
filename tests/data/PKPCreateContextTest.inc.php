<?php

/**
 * @file tests/data/PKPCreateContextTest.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CreateContextTest
 * @ingroup tests_data
 *
 * @brief Data build suite: Create and configure a test press
 */

import('lib.pkp.tests.WebTestCase');

use Facebook\WebDriver\Interactions\WebDriverActions;

abstract class PKPCreateContextTest extends WebTestCase {
	/** @var array */
	public $contextName = [];

	/** @var string journal or press*/
	public $contextType = '';

	/** @var array */
	public $contextDescription = [];

	/** @var array */
	public $contextAcronym = [];

	/**
	 * Create and set up test data
	 */
	function createContext() {
		$this->open(self::$baseUrl);

		$this->waitForElementPresent($selector='css=[id^=component-grid-admin-context-contextgrid-createContext-button-]');
		$this->click($selector);

		// Test required fields
		$this->click('//button[@label="Français (Canada)"]');
		$this->setInputValue('[name="name-fr_CA"]', $this->contextName['fr_CA']);
		$this->click('//button[@label="Français (Canada)"]');
		$this->click('//div[@id="editContext"]//button[contains(text(),"Save")]');
		$this->waitForElementPresent('//div[@id="context-name-error-en_US"]//span[contains(text(),"This field is required.")]');
		$this->waitForElementPresent('//div[@id="context-acronym-error-en_US"]//span[contains(text(),"This field is required.")]');
		$this->waitForElementPresent('//div[@id="context-urlPath-error"]//span[contains(text(),"This field is required.")]');
		$this->setInputValue('[name="name-en_US"]', $this->contextName['en_US']);
		$this->setInputValue('[name="acronym-en_US"]', $this->contextAcronym['en_US']);

		// Test invalid path characters
		$this->setInputValue('[name="urlPath"]', 'public&-)knowledge');
		$this->click('//div[@id="editContext"]//button[contains(text(),"Save")]');
		$this->waitForElementPresent('//div[@id="context-urlPath-error"]//span[contains(text(),"The path can only include letters")]');
		$this->setInputValue('[name="urlPath"]', 'publicknowledge');

		$this->typeTinyMCE('context-description-control-en_US', $this->contextDescription['en_US'], true);
		$this->typeTinyMCE('context-description-control-fr_CA', $this->contextDescription['fr_CA'], true);
		$this->click('//div[@id="editContext"]//button[contains(text(),"Save")]');
		$this->waitForElementPresent('//h1[contains(text(),"Settings Wizard")]');
	}

	/**
	 * Test the settings wizard
	 */
	function settingsWizard() {
		$this->open(self::$baseUrl);
		$actions = new WebDriverActions(self::$driver);
		$actions->moveToElement($this->waitForElementPresent('css=ul#navigationUser>li.profile>a'))
			->click($this->waitForElementPresent('//ul[@id="navigationUser"]//a[contains(text(),"Administration")]'))
			->perform();

		$this->click('//a[starts-with(text(),"Hosted")]');
		$this->waitForElementPresent($selector = 'css=a.show_extras');
		$this->click($selector);
		$this->waitForElementPresent($selector = 'link=Settings wizard');
		$this->click($selector);
		$this->waitForElementPresent('//h1[contains(text(),"Settings Wizard")]');

		$this->click('//button[contains(text(),"Appearance")]');
		$this->waitForElementPresent($selector = '//*[@id="appearance"]//button[contains(text(),"Save")]');
		$this->click($selector);
		$this->waitForTextPresent('The theme has been updated.');

		sleep(5); // FIXME: Avoid intermittent failure to scroll to top of page
		self::$driver->executeScript('window.scrollTo(0,0);'); // Scroll to top of page
		$this->click('//button[contains(text(),"Languages")]');
		$this->waitForElementPresent($selector = '//input[@id="select-cell-fr_CA-contextPrimary"]');
		$this->click($selector);
		$this->waitForTextPresent('Locale settings saved.');
		$this->click('css=input#select-cell-en_US-contextPrimary');

		$this->click('//button[contains(text(),"Search Indexing")]');
		$this->setInputValue('[name="searchDescription-en_US"]', $this->contextDescription);
		$this->setInputValue('[name="customHeaders-en_US"]', '<meta name="pkp" content="Test metatag.">');
		$this->click('//*[@id="indexing"]//button[contains(text(),"Save")]');
		$this->waitForTextPresent('The search engine index settings have been updated.');

		// Test the form tooltip
		sleep(5); // FIXME: Avoid intermittent failure to open tooltip
		$this->click('//label[@for="searchIndexing-searchDescription-control-en_US"]/../button[contains(@class,"tooltipButton")]');
		$this->waitForElementPresent('//div[starts-with(@id,"tooltip_")]//div[contains(text(),"Provide a brief description")]');
		$this->click('//label[@for="searchIndexing-searchDescription-control-en_US"]/../button[contains(@class,"tooltipButton")]');
	}

	/**
	 * Test the context contact settings
	 */
	function contactSettings() {
		self::$driver->executeScript('window.scrollTo(0,0);'); // Scroll to top of page
		$actions = new WebDriverActions(self::$driver);
		$actions->moveToElement($this->waitForElementPresent('//ul[@id="navigationPrimary"]//a[text()="Settings"]'))
			->click($this->waitForElementPresent('//ul[@id="navigationPrimary"]//a[text()="Journal" or text()="Press"]'))
			->perform();
		$this->click('//button[contains(text(),"Contact")]');


		// Required fields
		$this->waitForElementPresent($selector = '//*[@id="contact"]//button[contains(text(),"Save")]');
		$this->click($selector);
		$this->waitForElementPresent('//div[@id="contact-contactName-error"]//span[contains(text(),"This field is required.")]');
		$this->waitForElementPresent('//div[@id="contact-contactEmail-error"]//span[contains(text(),"This field is required.")]');
		$this->waitForElementPresent('//div[@id="contact-mailingAddress-error"]//span[contains(text(),"This field is required.")]');
		$this->waitForElementPresent('//div[@id="contact-supportName-error"]//span[contains(text(),"This field is required.")]');
		$this->waitForElementPresent('//div[@id="contact-supportEmail-error"]//span[contains(text(),"This field is required.")]');

		$this->setInputValue('[name="contactName"]', 'Ramiro Vaca');
		$this->setInputValue('[name="mailingAddress"]', "123 456th Street\nBurnaby, British Columbia\nCanada");
		$this->setInputValue('[name="supportName"]', 'Ramiro Vaca');

		// Invalid emails
		$this->setInputValue('[name="contactEmail"]', 'rvacamailinator.com');
		$this->setInputValue('[name="supportEmail"]', 'rvacamailinator.com');
		$this->click($selector);
		$this->waitForElementPresent('//div[@id="contact-contactEmail-error"]//span[contains(text(),"This is not a valid email address.")]');
		$this->waitForElementPresent('//div[@id="contact-supportEmail-error"]//span[contains(text(),"This is not a valid email address.")]');

		$this->setInputValue('[name="contactEmail"]', 'rvaca@mailinator.com');
		$this->setInputValue('[name="supportEmail"]', 'rvaca@mailinator.com');
		$this->click($selector);
		$this->waitForTextPresent('The contact details for this ' . $this->contextType . ' have been updated.');
	}
}
