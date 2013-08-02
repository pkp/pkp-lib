<?php

/**
 * @file tests/plugins/metadata/nlm30/filter/Nlm30CitationSchemaOpenurl10CrosswalkFilterTest.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Nlm30CitationSchemaOpenurl10CrosswalkFilterTest
 * @ingroup tests_plugins_metadata_nlm30_filter
 * @see Nlm30CitationSchemaOpenurl10CrosswalkFilter
 *
 * @brief Tests for the Nlm30CitationSchemaOpenurl10CrosswalkFilter class.
 */

import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30CitationSchemaOpenurl10CrosswalkFilter');
import('lib.pkp.tests.plugins.metadata.nlm30.filter.Nlm30Openurl10CrosswalkFilterTest');

class Nlm30CitationSchemaOpenurl10CrosswalkFilterTest extends Nlm30Openurl10CrosswalkFilterTest {
	/**
	 * @covers Nlm30CitationSchemaOpenurl10CrosswalkFilter
	 * @covers Nlm30Openurl10CrosswalkFilter
	 */
	public function testExecute() {
		$nlm30Description = $this->getTestNlm30Description();
		$openurl10Description = $this->getTestOpenurl10Description();

		$filter = new Nlm30CitationSchemaOpenurl10CrosswalkFilter();
		self::assertEquals($openurl10Description, $filter->execute($nlm30Description));
	}
}
?>
