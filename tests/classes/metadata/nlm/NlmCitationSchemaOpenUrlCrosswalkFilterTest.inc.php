<?php

/**
 * @file tests/plugins/metadata/nlm30/NlmCitationSchemaOpenUrlCrosswalkFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaOpenUrlCrosswalkFilterTest
 * @ingroup tests_classes_metadata_nlm
 * @see NlmCitationSchemaOpenUrlCrosswalkFilter
 *
 * @brief Tests for the NlmCitationSchemaOpenUrlCrosswalkFilter class.
 */

import('lib.pkp.plugins.metadata.nlm30.filter.NlmCitationSchemaOpenUrlCrosswalkFilter');
import('lib.pkp.tests.classes.metadata.nlm.OpenUrlCrosswalkFilterTest');

class NlmCitationSchemaOpenUrlCrosswalkFilterTest extends OpenUrlCrosswalkFilterTest {
	/**
	 * @covers NlmCitationSchemaOpenUrlCrosswalkFilter
	 * @covers OpenUrlCrosswalkFilter
	 */
	public function testExecute() {
		$nlmDescription = $this->getTestNlmDescription();
		$openUrlDescription = $this->getTestOpenUrlDescription();

		$filter = new NlmCitationSchemaOpenUrlCrosswalkFilter();
		self::assertEquals($openUrlDescription, $filter->execute($nlmDescription));
	}
}
?>
