<?php

/**
 * @file tests/classes/citation/output/vancouver/NlmCitationSchemaVancouverFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaVancouverFilterTest
 * @ingroup tests_classes_citation_output_vancouver
 * @see NlmCitationSchemaVancouverFilter
 *
 * @brief Tests for the NlmCitationSchemaVancouverFilter class.
 */

import('lib.pkp.classes.citation.output.vancouver.NlmCitationSchemaVancouverFilter');
import('lib.pkp.tests.classes.citation.output.NlmCitationSchemaCitationOutputFormatFilterTest');

class NlmCitationSchemaVancouverFilterTest extends NlmCitationSchemaCitationOutputFormatFilterTest {
	/*
	 * Implements abstract methods from NlmCitationSchemaCitationOutputFormatFilter
	 */
	protected function getFilterInstance() {
		return new NlmCitationSchemaVancouverFilter();
	}

	protected function getBookResultNoAuthor() {
		return array('<p>Mania de bater: A punição corporal doméstica de crianças e adolescentes no Brasil. São Paulo: Iglu; 2001.', '</p>');
	}

	protected function getBookResult() {
		return array('<p>Azevedo MA. Mania de bater: A punição corporal doméstica de crianças e adolescentes no Brasil. São Paulo: Iglu; 2001.', '</p>');
	}

	protected function getBookChapterResult() {
		return array('<p>Azevedo MA, Guerra V. Psicologia genética e lógica. In: Mania de bater: A punição corporal doméstica de crianças e adolescentes no Brasil. São Paulo: Iglu; 2001. p. 15-25.', '</p>');
	}

	protected function getBookChapterWithEditorResult() {
		return array('<p>Azevedo MA, Guerra V. Psicologia genética e lógica. In: Mania de bater: A punição corporal doméstica de crianças e adolescentes no Brasil. Banks-Leite L, editor. São Paulo: Iglu; 2001. p. 15-25.', '</p>');
	}

	protected function getBookChapterWithEditorsResult() {
		return array('<p>Azevedo MA, Guerra V. Psicologia genética e lógica. In: Mania de bater: A punição corporal doméstica de crianças e adolescentes no Brasil. Banks-Leite L, Velado Jr M, editors. 2nd ed. São Paulo: Iglu; 2001. p. 15-25.', '</p>');
	}

	protected function getJournalArticleResult() {
		return array('<p>Silva VA, dos Santos P. Etinobotânica Xucuru: espécies místicas. Biotemas 2000 Jun;15(1):45-57. PubMed PMID: 12140307. doi: 10146:55793-493.', '</p>');
	}

	protected function getJournalArticleWithMoreThanSevenAuthorsResult() {
		return array('<p>Silva VA, dos Santos P, Miller FH, Choi MJ, Angeli LL, Harland AA, et al. Etinobotânica Xucuru: espécies místicas. Biotemas 2000 Jun;15(1):45-57. PubMed PMID: 12140307. doi: 10146:55793-493.', '</p>');
	}

	protected function getConfProcResult() {
		return array('<p>Liu S. Defending against business crises with the help of intelligent agent based early warning solutions. Proceedings of The Seventh International Conference on Enterprise Information Systems. Miami, FL: 2005 [cited 2006 Aug 12]. Available from: http://www.iceis.org/iceis2005/abstracts_2005.htm', '</p>');
	}
}
?>
