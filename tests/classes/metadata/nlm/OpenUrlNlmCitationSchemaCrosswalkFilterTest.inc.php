<?php

/**
 * @file tests/classes/metadata/nlm/OpenUrlNlmCitationSchemaCrosswalkFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrlNlmCitationSchemaCrosswalkFilterTest
 * @ingroup tests_classes_metadata_nlm
 * @see OpenUrlNlmCitationSchemaCrosswalkFilter
 *
 * @brief Tests for the OpenUrlNlmCitationSchemaCrosswalkFilter class.
 */

// $Id$

import('lib.pkp.classes.metadata.nlm.OpenUrlNlmCitationSchemaCrosswalkFilter');
import('lib.pkp.tests.classes.metadata.nlm.OpenUrlCrosswalkFilterTest');

class OpenUrlNlmCitationSchemaCrosswalkFilterTest extends OpenUrlCrosswalkFilterTest {
	/**
	 * @covers OpenUrlNlmCitationSchemaCrosswalkFilter
	 * @covers OpenUrlCrosswalkFilter
	 */
	public function testExecute() {
		$openUrlDescription = $this->getTestOpenUrlDescription();
		$nlmDescription = $this->getTestNlmDescription();

		// Properties that are not part of the OpenURL
		// description must be removed from the NLM description
		// before we compare the two.
		self::assertTrue($nlmDescription->removeStatement('person-group[@person-group-type="editor"]'));
		self::assertTrue($nlmDescription->removeStatement('source', 'de_DE'));
		self::assertTrue($nlmDescription->removeStatement('article-title', 'de_DE'));
		self::assertTrue($nlmDescription->removeStatement('publisher-loc'));
		self::assertTrue($nlmDescription->removeStatement('publisher-name'));
		self::assertTrue($nlmDescription->removeStatement('pub-id[@pub-id-type="doi"]'));
		self::assertTrue($nlmDescription->removeStatement('pub-id[@pub-id-type="pmid"]'));
		self::assertTrue($nlmDescription->removeStatement('uri'));
		self::assertTrue($nlmDescription->removeStatement('comment'));
		self::assertTrue($nlmDescription->removeStatement('annotation'));

		$filter = new OpenUrlNlmCitationSchemaCrosswalkFilter();
		self::assertEquals($nlmDescription, $filter->execute($openUrlDescription));
	}
}
?>
