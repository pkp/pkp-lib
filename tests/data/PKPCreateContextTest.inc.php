<?php

/**
 * @file tests/data/PKPCreateContextTest.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CreateContextTest
 * @ingroup tests_data
 *
 * @brief Data build suite: Create and configure a test press
 */

import('lib.pkp.tests.WebTestCase');

class PKPCreateContextTest extends WebTestCase {
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
		$this->goToHostedContexts();

		$this->waitForElementPresent($selector='css=[id^=component-grid-admin-context-contextgrid-createContext-button-]');
		$this->click($selector);

		// Test required fields
		$this->setInputValue('[name="name-fr_CA"]', $this->contextName['fr_CA']);
		$this->click('css=#editContext button:contains(\'Save\')');
		$this->waitForElementPresent('css=#context-name-error-en_US:contains(\'This field is required.\')');
		$this->waitForElementPresent('css=#context-acronym-error-en_US:contains(\'This field is required.\')');
		$this->waitForElementPresent('css=#context-urlPath-error:contains(\'This field is required.\')');
		$this->setInputValue('[name="name-en_US"]', $this->contextName['en_US']);
		$this->setInputValue('[name="acronym-en_US"]', $this->contextAcronym['en_US']);

		// Test invalid path characters
		$this->setInputValue('[name="urlPath"]', 'public&-)knowledge');
		$this->click('css=#editContext button:contains(\'Save\')');
		$this->waitForElementPresent('css=#context-urlPath-error:contains(\'The path can only include letters\')');
		$this->setInputValue('[name="urlPath"]', 'publicknowledge');

		$this->typeTinyMCE('context-description-control-en_US', $this->contextDescription['en_US'], true);
		$this->typeTinyMCE('context-description-control-fr_CA', $this->contextDescription['fr_CA'], true);
		$this->clickAndWait('css=#editContext button:contains(\'Save\')');
		$this->waitForElementPresent('css=h1:contains(\'Settings Wizard\')');
	}

	/**
	 * Test the settings wizard
	 */
	function settingsWizard() {
		$this->goToHostedContexts();

		$this->waitForElementPresent($selector = 'css=a.show_extras');
		$this->click($selector);
		$this->waitForElementPresent($selector = 'link=Settings wizard');
		$this->clickAndWait($selector);
		$this->waitForElementPresent('css=h1:contains(\'Settings Wizard\')');

		$this->click('css=a:contains(\'Appearance\')');
		$this->waitForElementPresent($selector = 'css=#appearance button:contains(\'Save\')');
		$this->click($selector);
		$this->waitForTextPresent('The theme has been updated.');

		$this->click('css=a:contains(\'Languages\')');
		$this->waitForElementPresent($selector = 'css=input#select-cell-fr_CA-contextPrimary');
		$this->click($selector);
		$this->waitForTextPresent('Locale settings saved.');
		$this->click('css=input#select-cell-en_US-contextPrimary');

		$this->click('css=a:contains(\'Search Indexing\')');
		$this->setInputValue('[name="searchDescription-en_US"]', $this->contextDescription);
		$this->setInputValue('[name="customHeaders-en_US"]', '<meta name="pkp" content="Test metatag.">');
		$this->click('css=#search-indexing button:contains(\'Save\')');
		$this->waitForTextPresent('The search engine index settings have been updated.');

		// Test the form tooltip
		$this->click('css=label[for="searchIndexing-searchDescription-control-en_US"] + button.tooltipButton');
		$this->waitForElementPresent('css=div[id^="tooltip_"]:contains(\'Provide a brief description\')');
	}

	/**
	 * Test the context contact settings
	 */
	function contactSettings() {
		// Settings > [Journal|Press] > Contact
		$this->click('link=Contact');

		// Required fields
		$this->waitForElementPresent($selector = 'css=#contact button:contains(\'Save\')');
		$this->click($selector);
		$this->waitForElementPresent('css=#contact-contactName-error:contains(\'This field is required.\')');
		$this->waitForElementPresent('css=#contact-contactEmail-error:contains(\'This field is required.\')');
		$this->waitForElementPresent('css=#contact-mailingAddress-error:contains(\'This field is required.\')');
		$this->waitForElementPresent('css=#contact-supportName-error:contains(\'This field is required.\')');
		$this->waitForElementPresent('css=#contact-supportEmail-error:contains(\'This field is required.\')');

		$this->setInputValue('[name="contactName"]', 'Ramiro Vaca');
		$this->setInputValue('[name="mailingAddress"]', "123 456th Street\nBurnaby, British Columbia\nCanada");
		$this->setInputValue('[name="supportName"]', 'Ramiro Vaca');

		// Invalid emails
		$this->setInputValue('[name="contactEmail"]', 'rvacamailinator.com');
		$this->setInputValue('[name="supportEmail"]', 'rvacamailinator.com');
		$this->click($selector);
		$this->waitForElementPresent('css=#contact-contactEmail-error:contains(\'This is not a valid email address.\')');
		$this->waitForElementPresent('css=#contact-supportEmail-error:contains(\'This is not a valid email address.\')');

		$this->setInputValue('[name="contactEmail"]', 'rvaca@mailinator.com');
		$this->setInputValue('[name="supportEmail"]', 'rvaca@mailinator.com');
		$this->click($selector);
		$this->waitForTextPresent('The contact details for this ' . $this->contextType . ' have been updated.');
	}
}
