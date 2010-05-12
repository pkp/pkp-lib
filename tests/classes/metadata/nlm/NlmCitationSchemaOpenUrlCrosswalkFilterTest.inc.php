<?php

/**
 * @file tests/classes/metadata/nlm/NlmCitationSchemaOpenUrlCrosswalkFilterTest.inc.php
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

// $Id$

import('lib.pkp.classes.metadata.nlm.NlmCitationSchemaOpenUrlCrosswalkFilter');
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
