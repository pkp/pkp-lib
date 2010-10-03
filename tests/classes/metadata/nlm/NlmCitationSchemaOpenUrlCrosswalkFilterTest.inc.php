<?php

/**
 * @file tests/plugins/metadata/nlm30/Nlm30CitationSchemaOpenUrlCrosswalkFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Nlm30CitationSchemaOpenUrlCrosswalkFilterTest
 * @ingroup tests_classes_metadata_nlm
 * @see Nlm30CitationSchemaOpenUrlCrosswalkFilter
 *
 * @brief Tests for the Nlm30CitationSchemaOpenUrlCrosswalkFilter class.
 */

import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30CitationSchemaOpenUrlCrosswalkFilter');
import('lib.pkp.tests.classes.metadata.nlm.OpenUrlCrosswalkFilterTest');

class Nlm30CitationSchemaOpenUrlCrosswalkFilterTest extends OpenUrlCrosswalkFilterTest {
	/**
	 * @covers Nlm30CitationSchemaOpenUrlCrosswalkFilter
	 * @covers OpenUrlCrosswalkFilter
	 */
	public function testExecute() {
		$nlmDescription = $this->getTestNlm30Description();
		$openUrlDescription = $this->getTestOpenUrlDescription();

		$filter = new Nlm30CitationSchemaOpenUrlCrosswalkFilter();
		self::assertEquals($openUrlDescription, $filter->execute($nlmDescription));
	}
}
?>
