<?php

/**
 * @file tests/classes/citation/output/NlmCitationSchemaCitationOutputFormatFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaCitationOutputFormatFilterTest
 * @ingroup tests_classes_citation_output
 *
 * @brief Base tests class for citation output format filters.
 */

import('lib.pkp.tests.PKPTestCase');

import('lib.pkp.classes.core.PKPRouter');
import('lib.pkp.classes.core.PKPRequest');

import('lib.pkp.classes.metadata.nlm.NlmNameSchema');
import('lib.pkp.classes.metadata.nlm.NlmCitationSchema');
import('lib.pkp.classes.metadata.MetadataDescription');

class NlmCitationSchemaCitationOutputFormatFilterTest extends PKPTestCase {
	var $_request;

	//
	// Getters and setters
	//
	protected function getRequest() {
		return $this->_request;
	}

	protected function setUp() {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$router = new PKPRouter();
		$this->_request = new PKPRequest();
		$this->_request->setRouter($router);
	}

	public function testExecuteWithUnsupportedPublicationType() {
		$nameSchema = new NlmNameSchema();
		$citationSchema = new NlmCitationSchema();
		// Create a description with an unsupported publication type
		$citationDescription = new MetadataDescription($citationSchema, ASSOC_TYPE_CITATION);
		$citationDescription->addStatement('[@publication-type]', $pubType = NLM_PUBLICATION_TYPE_THESIS);
		$citationOutputFilter = $this->getFilterInstance();
		$result = $citationOutputFilter->execute($citationDescription);
		self::assertEquals('translated string', $result); // This is the string returned from the mock locale for all translations
		self::assertEquals(Locale::getTestedTranslationKey(), 'submission.citations.output.unsupportedPublicationType');
	}

	public function testExecuteWithBook() {
		$nameSchema = new NlmNameSchema();
		$citationSchema = new NlmCitationSchema();

		// Two representative authors
		$person1Description = new MetadataDescription($nameSchema, ASSOC_TYPE_AUTHOR);
		$person1Description->addStatement('surname', $surname = 'Azevedo');
		$person1Description->addStatement('given-names', $givenName1 = 'Mario');
		$person1Description->addStatement('given-names', $givenName2 = 'Antonio');
		$person2Description = new MetadataDescription($nameSchema, ASSOC_TYPE_AUTHOR);
		$person2Description->addStatement('surname', $surname2 = 'Guerra');
		$person2Description->addStatement('given-names', $givenName3 = 'Vitor');

		// Check a book with minimal data
		$citationDescription = new MetadataDescription($citationSchema, ASSOC_TYPE_CITATION);
		$citationDescription->addStatement('[@publication-type]', $pubType = NLM_PUBLICATION_TYPE_BOOK);
		$citationDescription->addStatement('source', $source = 'Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil');
		$citationDescription->addStatement('date', $date = '2001');
		$citationDescription->addStatement('publisher-loc', $pubLoc = 'São Paulo');
		$citationDescription->addStatement('publisher-name', $pubName = 'Iglu');
		$citationDescription->addStatement('size', $size = 368);
		$citationDescription->addStatement('series', $series = 'Edição Standard Brasileira das Obras Psicológicas');
		$citationDescription->addStatement('volume', $volume = '10');

		$citationOutputFilter = $this->getFilterInstance();

		// Book without author
		$result = $citationOutputFilter->execute($citationDescription);
		self::assertEquals($this->getBookResultNoAuthor(), $result);

		// Add an author
		$citationDescription->addStatement('person-group[@person-group-type="author"]', $person1Description);
		$result = $citationOutputFilter->execute($citationDescription);
		self::assertEquals($this->getBookResult(), $result);

		// Add a chapter title and a second author
		$citationDescription->addStatement('person-group[@person-group-type="author"]', $person2Description);
		$citationDescription->addStatement('chapter-title', $chapterTitle = 'Psicologia genética e lógica');
		$citationDescription->addStatement('fpage', $fpage = 15);
		$citationDescription->addStatement('lpage', $lpage = 25);
		$result = $citationOutputFilter->execute($citationDescription);
		self::assertEquals($this->getBookChapterResult(), $result);

		// Add editor
		$person3Description = new MetadataDescription($nameSchema, ASSOC_TYPE_EDITOR);
		$person3Description->addStatement('surname', $surname3 = 'Banks-Leite');
		$person3Description->addStatement('given-names', $givenName4 = 'Lorena');
		$citationDescription->addStatement('person-group[@person-group-type="editor"]', $person3Description);
		$result = $citationOutputFilter->execute($citationDescription);
		self::assertEquals($this->getBookChapterWithEditorResult(), $result);

		// Add another editor
		$person4Description = new MetadataDescription($nameSchema, ASSOC_TYPE_EDITOR);
		$person4Description->addStatement('surname', $surname3 = 'Velado');
		$person4Description->addStatement('given-names', $givenName4 = 'Mariano');
		$person4Description->addStatement('suffix', $givenName4 = 'Jr.');
		self::assertTrue($citationDescription->addStatement('person-group[@person-group-type="editor"]', $person4Description));
		$result = $citationOutputFilter->execute($citationDescription);
		self::assertEquals($this->getBookChapterWithEditorsResult(), $result);
	}

	public function testExecuteWithJournal() {
		$nameSchema = new NlmNameSchema();
		$citationSchema = new NlmCitationSchema();

		// Two representative authors
		$person1Description = new MetadataDescription($nameSchema, ASSOC_TYPE_AUTHOR);
		$person1Description->addStatement('surname', $surname = 'Silva');
		$person1Description->addStatement('given-names', $givenName1 = 'Vitor');
		$person1Description->addStatement('given-names', $givenName2 = 'Antonio');
		$person2Description = new MetadataDescription($nameSchema, ASSOC_TYPE_AUTHOR);
		$person2Description->addStatement('surname', $surname2 = 'Santos');
		$person2Description->addStatement('prefix', $prefix1 = 'dos');
		$person2Description->addStatement('given-names', $givenName3 = 'Pedro');

		// Check a journal article
		$citationDescription = new MetadataDescription($citationSchema, ASSOC_TYPE_CITATION);
		$citationDescription->addStatement('[@publication-type]', $pubType = NLM_PUBLICATION_TYPE_JOURNAL);
		$citationDescription->addStatement('person-group[@person-group-type="author"]', $person1Description);
		$citationDescription->addStatement('person-group[@person-group-type="author"]', $person2Description);
		$citationDescription->addStatement('article-title', $articleTitle = 'Etinobotânica Xucuru: espécies místicas');
		$citationDescription->addStatement('source', $source = 'Biotemas');
		$citationDescription->addStatement('publisher-loc', $pubLoc = 'Florianópolis');
		$citationDescription->addStatement('volume', $volume = '15');
		$citationDescription->addStatement('issue', $issue = '1');
		$citationDescription->addStatement('fpage', $fpage = 45);
		$citationDescription->addStatement('lpage', $lpage = 57);
		$citationDescription->addStatement('date', $date = '2000-06');
		$citationDescription->addStatement('pub-id[@pub-id-type="doi"]', $doi = '10146:55793-493');
		$citationOutputFilter = $this->getFilterInstance();
		$result = $citationOutputFilter->execute($citationDescription);
		self::assertEquals($this->getJournalArticleResult(), $result);

		// Add 6 more authors
		$authors = array(
			array('Miller', array('F', 'H')),
			array('Choi', array('M', 'J')),
			array('Angeli', array('L', 'L')),
			array('Harland', array('A', 'A')),
			array('Stamos', array('J', 'A')),
			array('Thomas', array('S', 'T'))
		);
		foreach ($authors as $author) {
			$personDescription = new MetadataDescription($nameSchema, ASSOC_TYPE_AUTHOR);
			$personDescription->addStatement('surname', $author[0]);
			$personDescription->addStatement('given-names', $author[1][0]);
			$personDescription->addStatement('given-names', $author[1][1]);
			$citationDescription->addStatement('person-group[@person-group-type="author"]', $personDescription);
			unset($personDescription);
		}
		$result = $citationOutputFilter->execute($citationDescription);
		self::assertEquals($this->getJournalArticleWithMoreThanSevenAuthorsResult(), $result);
	}

	//
	// Abstract protected template methods to be implemented by subclasses
	//
	/**
	 * @return Filter
	 */
	protected function getFilterInstance() {
		assert(false);
	}

	/**
	 * @return string
	 */
	protected function getBookResult() {
		assert(false);
	}

	/**
	 * @return string
	 */
	protected function getBookChapterResult() {
		assert(false);
	}

	/**
	 * @return string
	 */
	protected function getBookChapterWithEditorResult() {
		assert(false);
	}

	/**
	 * @return string
	 */
	protected function getJournalArticleResult() {
		assert(false);
	}
}
?>
