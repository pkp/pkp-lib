<?php

/**
 * @file tests/plugins/metadata/nlm30/OpenUrl10Nlm30CitationSchemaCrosswalkFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OpenUrl10Nlm30CitationSchemaCrosswalkFilterTest
 * @ingroup tests_classes_metadata_nlm
 * @see OpenUrl10Nlm30CitationSchemaCrosswalkFilter
 *
 * @brief Tests for the OpenUrl10Nlm30CitationSchemaCrosswalkFilter class.
 */

import('lib.pkp.plugins.metadata.nlm30.filter.OpenUrl10Nlm30CitationSchemaCrosswalkFilter');
import('lib.pkp.tests.classes.metadata.nlm.OpenUrl10CrosswalkFilterTest');

class OpenUrl10Nlm30CitationSchemaCrosswalkFilterTest extends OpenUrl10CrosswalkFilterTest {
	/**
	 * @covers OpenUrl10Nlm30CitationSchemaCrosswalkFilter
	 * @covers OpenUrl10CrosswalkFilter
	 */
	public function testExecute() {
		$openUrl10Description = $this->getTestOpenUrl10Description();
		$nlm30Description = $this->getTestNlm30Description();

		// Properties that are not part of the OpenURL
		// description must be removed from the NLM description
		// before we compare the two.
		self::assertTrue($nlm30Description->removeStatement('person-group[@person-group-type="editor"]'));
		self::assertTrue($nlm30Description->removeStatement('source', 'de_DE'));
		self::assertTrue($nlm30Description->removeStatement('article-title', 'de_DE'));
		self::assertTrue($nlm30Description->removeStatement('publisher-loc'));
		self::assertTrue($nlm30Description->removeStatement('publisher-name'));
		self::assertTrue($nlm30Description->removeStatement('pub-id[@pub-id-type="doi"]'));
		self::assertTrue($nlm30Description->removeStatement('pub-id[@pub-id-type="pmid"]'));
		self::assertTrue($nlm30Description->removeStatement('uri'));
		self::assertTrue($nlm30Description->removeStatement('comment'));
		self::assertTrue($nlm30Description->removeStatement('annotation'));

		$filter = new OpenUrl10Nlm30CitationSchemaCrosswalkFilter();
		self::assertEquals($nlm30Description, $filter->execute($openUrl10Description));
	}
}
?>
