<?php

/**
 * @defgroup tests_plugins_citationLookup_isbndb
 */

/**
 * @file tests/plugins/citationLookup/isbndb/PKPIsbndbCitationLookupPluginTest.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPIsbndbCitationLookupPluginTest
 * @ingroup tests_plugins_citationLookup_isbndb
 * @see PKPIsbndbCitationLookupPlugin
 *
 * @brief Test class for PKPIsbndbCitationLookupPlugin.
 */


import('lib.pkp.tests.plugins.PluginTestCase');

class PKPIsbndbCitationLookupPluginTest extends PluginTestCase {
	/**
	 * @covers PKPIsbndbCitationLookupPlugin
	 */
	public function testIsbndbCitationLookupPlugin() {
		// Delete the ISBNdb generic sequencer filter.
		$filterDao =& DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
		$filterFactory =& $filterDao->getObjectsByGroupAndClass('nlm30-element-citation=>nlm30-element-citation', 'lib.pkp.classes.filter.GenericSequencerFilter', 0, true);
		foreach($filterFactory->toArray() as $filter) {
			if ($filter->getDisplayName() == 'ISBNdb') $filterDao->deleteObject($filter);
		}

		$this->executePluginTest(
			'citationLookup',
			'isbndb',
			'IsbndbCitationLookupPlugin',
			array('nlm30-element-citation=>isbn', 'isbn=>nlm30-element-citation')
		);
	}
}
?>
