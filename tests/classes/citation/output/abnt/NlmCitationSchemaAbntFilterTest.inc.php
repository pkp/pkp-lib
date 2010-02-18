<?php

/**
 * @file tests/classes/citation/output/abnt/NlmCitationSchemaAbntFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaAbntFilterTest
 * @ingroup tests_classes_citation_output_abnt
 * @see NlmCitationSchemaAbntFilter
 *
 * @brief Tests for the NlmCitationSchemaAbntFilter class.
 */

// $Id$

import('tests.PKPTestCase');

import('core.PKPRouter');
import('core.PKPRequest');

import('metadata.nlm.NlmNameSchema');
import('metadata.nlm.NlmCitationSchema');
import('metadata.MetadataDescription');
import('citation.output.abnt.NlmCitationSchemaAbntFilter');

class NlmCitationSchemaAbntFilterTest extends PKPTestCase {
	var $_mockRequest;

	protected function setUp() {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$router = new PKPRouter();
		$this->_request = new PKPRequest();
		$this->_request->setRouter($router);
	}

	/**
	 * @covers NlmCitationSchemaAbntFilter
	 */
	public function testExecuteWithBook() {
		$nameSchema = new NlmNameSchema();
		$citationSchema = new NlmCitationSchema();

		// Two representative authors
		$author1Description = new MetadataDescription($nameSchema, ASSOC_TYPE_AUTHOR);
		$author1Description->addStatement('surname', $surname = 'Azevedo');
		$author1Description->addStatement('given-names', $givenName1 = 'Mario');
		$author1Description->addStatement('given-names', $givenName2 = 'Antonio');
		$author2Description = new MetadataDescription($nameSchema, ASSOC_TYPE_AUTHOR);
		$author2Description->addStatement('surname', $surname2 = 'Guerra');
		$author2Description->addStatement('given-names', $givenName3 = 'Vitor');

		// Check a book with minimal data
		$citationDescription = new MetadataDescription($citationSchema, ASSOC_TYPE_CITATION);
		$citationDescription->addStatement('[@publication-type]', $pubType = 'book');
		$citationDescription->addStatement('person-group[@person-group-type="author"]', $author1Description);
		$citationDescription->addStatement('person-group[@person-group-type="author"]', $author2Description);
		$citationDescription->addStatement('source', $source = 'Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil');
		$citationDescription->addStatement('date', $date = '2001');
		$citationDescription->addStatement('publisher-loc', $pubLoc = 'São Paulo');
		$citationDescription->addStatement('publisher-name', $pubName = 'Iglu');
		$citationDescription->addStatement('size', $size = 368);
		$citationDescription->addStatement('series', $series = 'Edição Standard Brasileira das Obras Psicológicas');
		$citationDescription->addStatement('volume', $volume = '10');

		$citationOutputFilter = new NlmCitationSchemaAbntFilter($this->_request);
		$result = $citationOutputFilter->execute($citationDescription);
		self::assertEquals('AZEVEDO, M.A.; GUERRA, V. <i>Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil.</i> São Paulo: Iglu, 1969. 368 p. (Edição Standard Brasileira das Obras Psicológicas, v.10)', $result);

		// Add a chapter title
		$citationDescription->addStatement('chapter-title', $chapterTitle = 'Psicologia genética e lógica');
		$result = $citationOutputFilter->execute($citationDescription);
		self::assertEquals('AZEVEDO, M.A.; GUERRA, V. Psicologia genética e lógica. In: ________. <i>Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil.</i> São Paulo: Iglu, 1969. 368 p. (Edição Standard Brasileira das Obras Psicológicas, v.10)', $result);

		// Add editor
		$editorDescription = new MetadataDescription($nameSchema, ASSOC_TYPE_EDITOR);
		$editorDescription->addStatement('surname', $surname3 = 'Banks-Leite');
		$editorDescription->addStatement('given-names', $givenName4 = 'Lorena');
		$citationDescription->addStatement('person-group[@person-group-type="editor"]', $editorDescription);
		$result = $citationOutputFilter->execute($citationDescription);
		self::assertEquals('AZEVEDO, M.A.; GUERRA, V. Psicologia genética e lógica. In: BANKS-LEITE, L. (Ed.). <i>Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil.</i> São Paulo: Iglu, 1969. 368 p. (Edição Standard Brasileira das Obras Psicológicas, v.10)', $result);
	}

	/**
	 * @covers NlmCitationSchemaAbntFilter
	 */
	public function testExecuteWithJournal() {
		$nameSchema = new NlmNameSchema();
		$citationSchema = new NlmCitationSchema();

		// Two representative authors
		$author1Description = new MetadataDescription($nameSchema, ASSOC_TYPE_AUTHOR);
		$author1Description->addStatement('surname', $surname = 'Silva');
		$author1Description->addStatement('given-names', $givenName1 = 'Vitor');
		$author1Description->addStatement('given-names', $givenName2 = 'Antonio');
		$author2Description = new MetadataDescription($nameSchema, ASSOC_TYPE_AUTHOR);
		$author2Description->addStatement('surname', $surname2 = 'Santos');
		$author2Description->addStatement('prefix', $prefix1 = 'dos');
		$author2Description->addStatement('given-names', $givenName3 = 'Pedro');

		// Check a journal article
		$citationDescription = new MetadataDescription($citationSchema, ASSOC_TYPE_CITATION);
		$citationDescription->addStatement('[@publication-type]', $pubType = 'journal');
		$citationDescription->addStatement('person-group[@person-group-type="author"]', $author1Description);
		$citationDescription->addStatement('person-group[@person-group-type="author"]', $author2Description);
		$citationDescription->addStatement('article-title', $articleTitle = 'Etinobotânica Xucuru: espécies místicas');
		$citationDescription->addStatement('source', $source = 'Biotemas');
		$citationDescription->addStatement('publisher-loc', $pubLoc = 'Florianópolis');
		$citationDescription->addStatement('volume', $volume = '15');
		$citationDescription->addStatement('issue', $issue = '1');
		$citationDescription->addStatement('fpage', $fpage = 45);
		$citationDescription->addStatement('lpage', $lpage = 57);
		$citationDescription->addStatement('date', $date = '2000-06');

		$citationOutputFilter = new NlmCitationSchemaAbntFilter($this->_request);
		$result = $citationOutputFilter->execute($citationDescription);
		self::assertEquals('SILVA, V.A.; DOS SANTOS, P. Etinobotânica Xucuru: espécies místicas. <i>Biotemas,</i> Florianópolis, v.15, n.1, p.45-57, jun. 2000.', $result);
	}
}
?>
