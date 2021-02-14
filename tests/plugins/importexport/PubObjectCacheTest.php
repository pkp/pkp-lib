<?php

/**
 * @file tests/plugins/importexport/PubObjectCacheTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PubObjectCacheTest
 * @ingroup tests_plugins_importexport
 * @see PubObjectCacheTest
 *
 * @brief Test class for PubObjectCache.
 *
 * NB: This test is not in the medra or datacite package as the class
 * is used symlinked in both plug-ins.
 */

import('lib.pkp.tests.PKPTestCase');
import('classes/issue/Issue');
import('classes/preprint/Submission');
import('classes/preprint/PreprintGalley');
import('plugins.importexport.medra.classes.PubObjectCache');

class PubObjectCacheTest extends PKPTestCase {
	/**
	 * @covers PubObjectCache
	 */
	public function testAddIssue() {
		$nullVar = null;
		$cache = new PubObjectCache();

		$issue = new Issue();
		$issue->setId('1');

		self::assertFalse($cache->isCached('issues', $issue->getId()));
		$cache->add($issue, $nullVar);
		self::assertTrue($cache->isCached('issues', $issue->getId()));

		$retrievedIssue = $cache->get('issues', $issue->getId());
		self::assertEquals($issue, $retrievedIssue);
	}

	/**
	 * @covers PubObjectCache
	 */
	public function testAddPreprint() {
		$nullVar = null;
		$cache = new PubObjectCache();

		$preprint = new Submission();
		$preprint->setId('2');
		$preprint->setIssueId('1');

		self::assertFalse($cache->isCached('preprints', $preprint->getId()));
		self::assertFalse($cache->isCached('preprintsByIssue', $preprint->getCurrentPublication()->getData('issueId')));
		self::assertFalse($cache->isCached('preprintsByIssue', $preprint->getCurrentPublication()->getData('issueId'), $preprint->getId()));
		$cache->add($preprint, $nullVar);
		self::assertTrue($cache->isCached('preprints', $preprint->getId()));
		self::assertFalse($cache->isCached('preprintsByIssue', $preprint->getCurrentPublication()->getData('issueId')));
		self::assertTrue($cache->isCached('preprintsByIssue', $preprint->getCurrentPublication()->getData('issueId'), $preprint->getId()));

		$retrievedPreprint = $cache->get('preprints', $preprint->getId());
		self::assertEquals($preprint, $retrievedPreprint);
	}


	/**
	 * @covers PubObjectCache
	 */
	public function testAddGalley() {
		$nullVar = null;
		$cache = new PubObjectCache();

		$preprint = new Submission();
		$preprint->setId('2');
		$preprint->setIssueId('1');

		$preprintGalley = new PreprintGalley();
		$preprintGalley->setId('3');
		$preprintGalley->setSubmissionId($preprint->getId());

		self::assertFalse($cache->isCached('galleys', $preprintGalley->getId()));
		self::assertFalse($cache->isCached('galleysByPreprint', $preprint->getId()));
		self::assertFalse($cache->isCached('galleysByPreprint', $preprint->getId(), $preprintGalley->getId()));
		self::assertFalse($cache->isCached('galleysByIssue', $preprint->getCurrentPublication()->getData('issueId')));
		self::assertFalse($cache->isCached('galleysByIssue', $preprint->getCurrentPublication()->getData('issueId'), $preprintGalley->getId()));
		$cache->add($preprintGalley, $preprint);
		self::assertTrue($cache->isCached('galleys', $preprintGalley->getId()));
		self::assertFalse($cache->isCached('galleysByPreprint', $preprint->getId()));
		self::assertTrue($cache->isCached('galleysByPreprint', $preprint->getId(), $preprintGalley->getId()));
		self::assertFalse($cache->isCached('galleysByIssue', $preprint->getCurrentPublication()->getData('issueId')));
		self::assertTrue($cache->isCached('galleysByIssue', $preprint->getCurrentPublication()->getData('issueId'), $preprintGalley->getId()));

		$retrievedPreprintGalley1 = $cache->get('galleys', $preprintGalley->getId());
		self::assertEquals($preprintGalley, $retrievedPreprintGalley1);

		$retrievedPreprintGalley2 = $cache->get('galleysByIssue', $preprint->getCurrentPublication()->getData('issueId'), $preprintGalley->getId());
		self::assertEquals($retrievedPreprintGalley1, $retrievedPreprintGalley2);

		$cache->markComplete('galleysByPreprint', $preprint->getId());
		self::assertTrue($cache->isCached('galleysByPreprint', $preprint->getId()));
		self::assertFalse($cache->isCached('galleysByIssue', $preprint->getCurrentPublication()->getData('issueId')));
	}

	/**
	 * @covers PubObjectCache
	 */
	public function testAddSeveralGalleys() {
		$nullVar = null;
		$cache = new PubObjectCache();

		$preprint = new Submission();
		$preprint->setId('2');
		$preprint->setIssueId('1');

		$preprintGalley1 = new PreprintGalley();
		$preprintGalley1->setId('3');
		$preprintGalley1->setSubmissionId($preprint->getId());

		$preprintGalley2 = new PreprintGalley();
		$preprintGalley2->setId('4');
		$preprintGalley2->setSubmissionId($preprint->getId());

		// Add galleys in the wrong order.
		$cache->add($preprintGalley2, $preprint);
		$cache->add($preprintGalley1, $preprint);

		$cache->markComplete('galleysByPreprint', $preprint->getId());

		// Retrieve them in the right order.
		$retrievedGalleys = $cache->get('galleysByPreprint', $preprint->getId());
		$expectedGalleys = array(
			3 => $preprintGalley1,
			4 => $preprintGalley2
		);
		self::assertEquals($expectedGalleys, $retrievedGalleys);

		// And they should still be cached.
		self::assertTrue($cache->isCached('galleysByPreprint', $preprint->getId()));
	}
}

