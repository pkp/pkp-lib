<?php

/**
 * @file tests/classes/filter/FilterGroupDAOTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FilterGroupDAOTest
 * @ingroup tests_classes_filter
 * @see FilterGroupDAO
 *
 * @brief Test class for FilterGroupDAO.
 */

import('lib.pkp.tests.DatabaseTestCase');
import('lib.pkp.classes.filter.FilterGroup');
import('lib.pkp.classes.filter.FilterGroupDAO');

class FilterGroupDAOTest extends DatabaseTestCase {
	/**
	 * @covers FilterGroupDAO
	 */
	public function testFilterGroupCrud() {
		$filterGroupDao = DAORegistry::getDAO('FilterGroupDAO');

		// Instantiate a test filter group object
		$testFilterGroup = new FilterGroup();
		$testFilterGroup->setSymbolic('some-symbol');
		$testFilterGroup->setDisplayName('display name');
		$testFilterGroup->setTransformationType($in = 'primitive::string', $out = 'primitive::integer');

		// Insert filter group instance
		$filterGroupId = $filterGroupDao->insertObject($testFilterGroup, 9999);
		self::assertTrue(is_numeric($filterGroupId));
		self::assertTrue($filterGroupId > 0);

		// Retrieve filter group instance by id
		$filterGroupById = $filterGroupDao->getObjectById($filterGroupId);
		self::assertEquals($testFilterGroup, $filterGroupById);

		// Update filter group instance
		$testFilterGroup->setSymbolic('some-other-symbol');
		$testFilterGroup->setDisplayName('other display name');
		$testFilterGroup->setTransformationType($in = 'primitive::integer', $out = 'primitive::string');

		$filterGroupDao->updateObject($testFilterGroup);
		$filterGroupAfterUpdate = $filterGroupDao->getObject($testFilterGroup);
		self::assertEquals($testFilterGroup, $filterGroupAfterUpdate);

		// Delete filter group instance
		$filterGroupDao->deleteObjectById($filterGroupId);
		self::assertNull($filterGroupDao->getObjectById($filterGroupId));
	}
}
?>