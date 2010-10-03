<?php

/**
 * @file tests/plugins/metadata/nlm30/Nlm30CitationSchemaOpenUrl10CrosswalkFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Nlm30CitationSchemaOpenUrl10CrosswalkFilterTest
 * @ingroup tests_classes_metadata_nlm
 * @see Nlm30CitationSchemaOpenUrl10CrosswalkFilter
 *
 * @brief Tests for the Nlm30CitationSchemaOpenUrl10CrosswalkFilter class.
 */

import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30CitationSchemaOpenUrl10CrosswalkFilter');
import('lib.pkp.tests.classes.metadata.nlm.OpenUrl10CrosswalkFilterTest');

class Nlm30CitationSchemaOpenUrl10CrosswalkFilterTest extends OpenUrl10CrosswalkFilterTest {
	/**
	 * @covers Nlm30CitationSchemaOpenUrl10CrosswalkFilter
	 * @covers OpenUrl10CrosswalkFilter
	 */
	public function testExecute() {
		$nlmDescription = $this->getTestNlm30Description();
		$openUrlDescription = $this->getTestOpenUrl10Description();

		$filter = new Nlm30CitationSchemaOpenUrl10CrosswalkFilter();
		self::assertEquals($openUrlDescription, $filter->execute($nlmDescription));
	}
}
?>
