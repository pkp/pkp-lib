<?php

/**
 * @file tests/classes/filter/FilterDAOTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FilterDAOTest
 * @ingroup tests_classes_filter
 * @see FilterDAO
 *
 * @brief Test class for FilterDAO.
 */

import('lib.pkp.tests.DatabaseTestCase');
import('lib.pkp.classes.filter.FilterDAO');
import('lib.pkp.classes.citation.lookup.worldcat.WorldcatNlmCitationSchemaFilter');
import('lib.pkp.tests.classes.filter.CompatibilityTestFilter');

class FilterDAOTest extends DatabaseTestCase {
	/**
	 * @covers FilterDAO
	 */
	public function testFilterCrud() {
		$filterDAO = DAORegistry::getDAO('FilterDAO');

		// Instantiate a test filter object
		$testFilter = new WorldcatNlmCitationSchemaFilter('some api key');
		$testFilter->setSeq(3);

		// Insert filter instance
		$filterId = $filterDAO->insertObject($testFilter);
		self::assertTrue(is_numeric($filterId));
		self::assertTrue($filterId > 0);

		// Retrieve filter instance by id
		$filterById = $filterDAO->getObjectById($filterId);
		self::assertEquals($testFilter, $filterById);

		// Update filter instance
		$testFilter->setData('apiKey', 'another api key');
		$testFilter->setIsTemplate(true);

		$filterDAO->updateObject($testFilter);
		$filterAfterUpdate = $filterDAO->getObjectById($filterId);
		self::assertEquals($testFilter, $filterAfterUpdate);

		// Delete filter instance
		$filterDAO->deleteObject($testFilter);
		self::assertNull($filterDAO->getObjectById($filterId));
	}

	/**
	 * @covers FilterDAO::getCompatibleObjects
	 * @depends testFilterCrud
	 */
	public function testGetCompatibleObjects() {
		$filterDAO = DAORegistry::getDAO('FilterDAO');

		// Instantiate test filter objects
		$testFilters = array(
			new CompatibilityTestFilter('TestFilter1', array('primitive::string', 'primitive::string')),
			new CompatibilityTestFilter('TestFilter2', array('primitive::string', 'validator::date('.DATE_FORMAT_ISO.')')),
			new CompatibilityTestFilter('TestFilter3', array('metadata::lib.pkp.classes.metadata.NlmCitationSchema(CITATION)', 'primitive::string')),
			new CompatibilityTestFilter('TestFilter4', array('validator::date('.DATE_FORMAT_ISO.')', 'primitive::string')),
			new CompatibilityTestFilter('TestFilter5', array('primitive::string', 'primitive::integer'))
		);

		// Introduce an impossible runtime condition in one filter
		// that would otherwise be selected to test the runtime condition
		// checking.
		$testFilters[3]->setData('phpVersionMax', '2.0.0');

		// Persist test filters
		$testFilterIds = array();
		foreach($testFilters as $testFilter) {
			$testFilterIds[] = $filterDAO->insertObject($testFilter);
		}

		// Test compatibility
		$inputSample = '2011-01-01';
		$outputSample = '2009-10-04';

		$compatibleFilters = $filterDAO->getCompatibleObjects($inputSample, $outputSample);
		$returnedFilters = array();
		foreach($compatibleFilters as $compatibleFilter) {
			$returnedFilters[] = $compatibleFilter->getDisplayName();
		}
		sort($returnedFilters);
		$expectedFilters = array('TestFilter1', 'TestFilter2');
		self::assertEquals($expectedFilters, $returnedFilters);

		// Delete test filters
		foreach($testFilterIds as $testFilterId) {
			$filterDAO->deleteObjectById($testFilterId);
		}
	}
}
?>