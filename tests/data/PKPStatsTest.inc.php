<?php

/**
 * @file tests/data/70-StatsTest.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class StatsTest
 * @ingroup tests_data
 *
 * @brief Data build suite: Create stats
 */

import('lib.pkp.tests.WebTestCase');
import('classes.core.Services');

use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverBy;

abstract class PKPStatsTest extends WebTestCase {
  /**
   * Generate usage stats
   */
  public function generateUsageStats() {

		// Generate some usage statistics for the last 90 days
		import('lib.pkp.classes.submission.Submission');
		import('classes.statistics.StatisticsHelper');
		$metricsDao = DAORegistry::getDAO('MetricsDAO');
		$submissionIds = array_map(function($submission) {
			return $submission->getId();
		}, Services::get('submission')->getMany(['contextId' => 1, 'status' => STATUS_PUBLISHED]));
		$currentDate = new DateTime();
		$currentDate->sub(new DateInterval('P90D'));
		$dateEnd = new DateTime();
		while ($currentDate->getTimestamp() < $dateEnd->getTimestamp()) {
			foreach ($submissionIds as $submissionId) {
				$metricsDao->insertRecord([
					'load_id' => 'test_events_' . $currentDate->format('Ymd'),
					'assoc_type' => ASSOC_TYPE_SUBMISSION,
					'assoc_id' => $submissionId,
					'submission_id' => $submissionId,
					'metric_type' => METRIC_TYPE_COUNTER,
					'metric' => rand(5, 10),
					'day' => $currentDate->format('Ymd'),
				]);
			}
			$currentDate->add(new DateInterval('P1D'));
		}
  }

  /**
   * Open a stats page
   */
  public function goToStats($username, $password, $menuItemTitle) {
		$this->open(self::$baseUrl);
		$this->logIn($username, $password);
		$actions = new WebDriverActions(self::$driver);
		$actions->moveToElement($this->waitForElementPresent('//ul[@id="navigationPrimary"]//a[contains(text(), "Statistics")]'))
			->click($this->waitForElementPresent('//ul[@id="navigationPrimary"]//a[contains(text(), "' . $menuItemTitle . '")]'))
			->perform();
  }

  /**
   * Test the date range selection and the chart
   */
  public function checkGraph($totalAbstractViews, $abstractViews, $files, $totalFileViews, $fileViews) {
		$yesterday =  date('Y-m-d', strtotime('yesterday'));
		$daysAgo90 = date('Y-m-d', strtotime('-91 days'));
		$daysAgo10 = date('Y-m-d', strtotime('-10 days'));
		$daysAgo50 = date('Y-m-d', strtotime('-50 days'));
		$this->waitForElementPresent($dateRangeToggle = '//button[@class="pkpDateRange__button"]');
		$this->click($dateRangeToggle);
		$this->waitForElementPresent($selector='//button[contains(text(), "Last 90 days")]');
		$this->click($selector);
		self::$driver->wait()->until(WebDriverExpectedCondition::invisibilityOfElementLocated(WebDriverBy::cssSelector('.pkpDateRange__options')));
		$this->waitForElementPresent('//span[contains(text(), "' . $daysAgo90 . ' — ' . $yesterday .'")]');
		$this->click($dateRangeToggle);
		$this->waitForElementPresent($selector='css=.pkpDateRange__input--start');
		$this->type($selector, $daysAgo50);
		$this->type('css=.pkpDateRange__input--end', $daysAgo10);
		$this->click('//button[contains(text(), "Apply")]');
		self::$driver->wait()->until(WebDriverExpectedCondition::invisibilityOfElementLocated(WebDriverBy::cssSelector('.pkpDateRange__options')));
		$this->waitForElementPresent('//span[contains(text(), "' . $daysAgo50 . ' — ' . $daysAgo10 .'")]');

		// Test that the hidden timeline table for screen readers is getting populated
		// with rows of content.
		self::$driver->executeScript('$(".pkpStats__graph table.-screenReader").removeClass("-screenReader");');
		$this->waitForElementPresent('//div[@class="pkpStats__graph"]//table/caption[contains(text(), "' . $totalAbstractViews .'")]');
		$this->waitForElementPresent('//div[@class="pkpStats__graph"]//table/thead/tr/th[contains(text(), "' . $abstractViews . '")]');
		$currentDate = DateTime::createFromFormat('Y-m-d', $daysAgo50);
		while ($currentDate->getTimestamp() < strtotime($daysAgo10)) {
			$dayLabel = strftime(Config::getVar('general', 'date_format_long'), $currentDate->getTimestamp());
			$this->waitForElementPresent('//div[@class="pkpStats__graph"]//table/tbody/tr/th[contains(text(),"' . $dayLabel . '")]');
			$currentDate->add(new DateInterval('P1D'));
    }
		$this->waitForElementPresent($selector = '//div[@class="pkpStats__graphSelectors"]//button[contains(text(), "Monthly")]');
		$this->click($selector);
		$this->waitForElementPresent($selector = '//div[@class="pkpStats__graphSelectors"]//button[contains(text(), "' . $files . '")]');
		$this->click($selector);
		$this->waitForElementPresent('//div[@class="pkpStats__graph"]//table/caption[contains(text(), "' . $totalFileViews . '")]');
		$this->waitForElementPresent('//div[@class="pkpStats__graph"]//table/thead/tr/th[contains(text(), "' . $fileViews . '")]');
		self::$driver->executeScript('$(".pkpStats__graph table[aria-live]").addClass("-screenReader");');
  }

  /**
   * Test the publication details table
   */
  public function checkTable($articleDetails, $articles, $authors) {
		$this->waitForElementPresent('//h2[contains(text(), "' . $articleDetails . '")]');
		$this->waitForElementPresent('//div[contains(text(), "2 of 2 ' . $articles . '")]');
		foreach ($authors as $author) {
			$this->waitForElementPresent('//div[@class="pkpStats__table"]//td[@class="pkpTable__cell"]//span[contains(text(), "' . $author . '")]');
		}
		$this->waitForElementPresent($selector = 'css=.pkpSearch__input');
		$this->type($selector, 'shouldreturnzeromatches');
		$this->waitForElementPresent('//div[contains(text(), "No ' . $articles . ' were found with usage statistics matching these parameters.")]');
		$this->waitForElementPresent('//div[contains(text(), "0 of 0 ' . $articles . '")]');
		$this->type($selector, $authors[0]);
		$this->waitForElementPresent('//div[@class="pkpStats__table"]//td[@class="pkpTable__cell"]//span[contains(text(), "' . $authors[0] . '")]');
		$this->waitForElementPresent('//div[contains(text(), "1 of 1 ' . $articles . '")]');
		$this->type($selector, '');
  }

  /**
   * Test the stats filters
   */
  public function checkFilters($filters) {
		$this->waitForElementPresent($selector = '//button[contains(text(), "Filters")]');
    $this->click($selector);
    foreach ($filters as $filter) {
      $this->waitForElementPresent('//div[@class="pkpStats__filterSet"]//button[contains(text(), "' . $filter . '")]');
    }
		$this->click($selector); // Close filters
  }

}
