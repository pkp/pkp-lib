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
import('lib.pkp.classes.filter.GenericSequencerFilter');
import('lib.pkp.classes.filter.GenericMultiplexerFilter');
import('lib.pkp.classes.citation.lookup.worldcat.WorldcatNlm30CitationSchemaFilter');
import('lib.pkp.tests.classes.filter.CompatibilityTestFilter');

class FilterDAOTest extends DatabaseTestCase {
	/**
	 * @covers FilterDAO
	 */
	public function testFilterCrud() {
		$filterDao = DAORegistry::getDAO('FilterDAO');

		// Instantiate a test filter object
		$testFilter = new WorldcatNlm30CitationSchemaFilter('some api key');
		$testFilter->setSeq(9999);

		// Insert filter instance
		$filterId = $filterDao->insertObject($testFilter, 9999);
		self::assertTrue(is_numeric($filterId));
		self::assertTrue($filterId > 0);

		// Retrieve filter instance by id
		$filterById = $filterDao->getObjectById($filterId);
		self::assertEquals($testFilter, $filterById);

		// Update filter instance
		$testFilter->setData('apiKey', 'another api key');
		$testFilter->setIsTemplate(true);

		$filterDao->updateObject($testFilter);
		$filterAfterUpdate = $filterDao->getObject($testFilter);
		self::assertEquals($testFilter, $filterAfterUpdate);

		// Delete filter instance
		$filterDao->deleteObject($testFilter);
		self::assertNull($filterDao->getObjectById($filterId));
	}

	public function testCompositeFilterCrud() {
		$filterDao = DAORegistry::getDAO('FilterDAO');

		// Instantiate a composite test filter object
		$transformation = array(
			'primitive::string',
			'primitive::string'
		);
		$testFilter = new GenericSequencerFilter('composite filter', $transformation);
		$testFilter->setSeq(9999);

		// sub-filter 1
		$subFilter1 = new CompatibilityTestFilter('1st sub-filter', $transformation);
		$testFilter->addFilter($subFilter1);

		// sub-filter 2
		$subFilter2 = new GenericMultiplexerFilter('2nd sub-filter', $transformation);
		$subSubFilter1 = new CompatibilityTestFilter('1st sub-sub-filter', $transformation);
		$subFilter2->addFilter($subSubFilter1);
		$subSubFilter2 = new CompatibilityTestFilter('2nd sub-sub-filter', $transformation);
		$subFilter2->addFilter($subSubFilter2);
		$testFilter->addFilter($subFilter2);

		// Insert filter instance
		$filterId = $filterDao->insertObject($testFilter, 9999);
		self::assertTrue(is_numeric($filterId));
		self::assertTrue($filterId > 0);

		// Check that sub-filters were correctly
		// linked to the composite filter.
		$subFilters =& $testFilter->getFilters();
		self::assertEquals(2, count($subFilters));
		foreach($subFilters as $subFilter) {
			self::assertTrue($subFilter->getId() > 0);
			self::assertEquals($filterId, $subFilter->getParentFilterId());
		}
		$subSubFilters =& $subFilters[2]->getFilters();
		self::assertEquals(2, count($subSubFilters));
		foreach($subSubFilters as $subSubFilter) {
			self::assertTrue($subSubFilter->getId() > 0);
			self::assertEquals($subFilters[2]->getId(), $subSubFilter->getParentFilterId());
		}

		// Retrieve filter instance by id
		$filterById = $filterDao->getObjectById($filterId);
		self::assertEquals($testFilter, $filterById);

		// Update filter instance
		$testFilter = new GenericSequencerFilter('composite filter', $transformation);
		$testFilter->setSeq(9999);
		$testFilter->setId($filterId);
		$testFilter->setIsTemplate(true);

		// leave out (sub-)sub-filter 2 but add a new (sub-)sub-filter 3
		// to test recursive update.
		$testFilter->addFilter($subFilter1);
		$subFilter3 = new GenericMultiplexerFilter('3rd sub-filter', $transformation);
		$subFilter3->addFilter($subSubFilter1);
		$subSubFilter3 = new CompatibilityTestFilter('3rd sub-sub-filter', $transformation);
		$subFilter3->addFilter($subSubFilter3);
		$testFilter->addFilter($subFilter3);

		$filterDao->updateObject($testFilter);
		$filterAfterUpdate = $filterDao->getObject($testFilter);
		self::assertEquals($testFilter, $filterAfterUpdate);

		// Delete filter instance
		$filterDao->deleteObject($testFilter);
		self::assertNull($filterDao->getObjectById($filterId));
	}

	/**
	 * @covers FilterDAO::getCompatibleObjects
	 * @depends testFilterCrud
	 */
	public function testGetCompatibleObjects() {
		$filterDao = DAORegistry::getDAO('FilterDAO');

		// Instantiate test filter objects
		$testFilters = array(
			new CompatibilityTestFilter('TestFilter1', array('primitive::string', 'primitive::string')),
			new CompatibilityTestFilter('TestFilter2', array('primitive::string', 'validator::date('.DATE_FORMAT_ISO.')')),
			new CompatibilityTestFilter('TestFilter3', array('metadata::lib.pkp.classes.metadata.Nlm30CitationSchema(CITATION)', 'primitive::string')),
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
			$testFilterIds[] = $filterDao->insertObject($testFilter, 9999);
		}

		// Test compatibility
		$inputSample = '2011-01-01';
		$outputSample = '2009-10-04';

		$compatibleFilters = $filterDao->getCompatibleObjects($inputSample, $outputSample, 9999);
		$returnedFilters = array();
		foreach($compatibleFilters as $compatibleFilter) {
			$returnedFilters[] = $compatibleFilter->getDisplayName();
		}
		sort($returnedFilters);
		$expectedFilters = array('TestFilter1', 'TestFilter2');
		self::assertEquals($expectedFilters, $returnedFilters);

		// Delete test filters
		foreach($testFilterIds as $testFilterId) {
			$filterDao->deleteObjectById($testFilterId);
		}
	}
}
?>