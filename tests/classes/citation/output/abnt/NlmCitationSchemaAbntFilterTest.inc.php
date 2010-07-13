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


import('lib.pkp.classes.citation.output.abnt.NlmCitationSchemaAbntFilter');
import('lib.pkp.tests.classes.citation.output.NlmCitationSchemaCitationOutputFormatFilterTest');

class NlmCitationSchemaAbntFilterTest extends NlmCitationSchemaCitationOutputFormatFilterTest {
	/*
	 * Implements abstract methods from NlmCitationSchemaCitationOutputFormatFilter
	 */
	protected function getFilterInstance() {
		return new NlmCitationSchemaAbntFilter($this->getRequest());
	}

	protected function getBookResultNoAuthor() {
		return '<i>Mania de bater:</i> A punição corporal doméstica de crianças e adolescentes no Brasil. São Paulo: Iglu, 2001. 368 p. (Edição Standard Brasileira das Obras Psicológicas, v.10)';
	}

	protected function getBookResult() {
		return 'AZEVEDO, M.A. <i>Mania de bater:</i> A punição corporal doméstica de crianças e adolescentes no Brasil. São Paulo: Iglu, 2001. 368 p. (Edição Standard Brasileira das Obras Psicológicas, v.10)';
	}

	protected function getBookChapterResult() {
		return 'AZEVEDO, M.A.; GUERRA, V. Psicologia genética e lógica. In: ________. <i>Mania de bater:</i> A punição corporal doméstica de crianças e adolescentes no Brasil. São Paulo: Iglu, 2001. 368 p. (Edição Standard Brasileira das Obras Psicológicas, v.10)';
	}

	protected function getBookChapterWithEditorResult() {
		return 'AZEVEDO, M.A.; GUERRA, V. Psicologia genética e lógica. In: BANKS-LEITE, L. (Ed.). <i>Mania de bater:</i> A punição corporal doméstica de crianças e adolescentes no Brasil. São Paulo: Iglu, 2001. 368 p. (Edição Standard Brasileira das Obras Psicológicas, v.10)';
	}

	protected function getBookChapterWithEditorsResult() {
		return 'AZEVEDO, M.A.; GUERRA, V. Psicologia genética e lógica. In: BANKS-LEITE, L.; VELADO, JR M. (Ed.). <i>Mania de bater:</i> A punição corporal doméstica de crianças e adolescentes no Brasil. São Paulo: Iglu, 2001. 368 p. (Edição Standard Brasileira das Obras Psicológicas, v.10)';
	}

	protected function getJournalArticleResult() {
		return 'SILVA, V.A.; DOS SANTOS, P. Etinobotânica Xucuru: espécies místicas. <i>Biotemas</i>, Florianópolis, v.15, n.1, p.45-57, jun 2000. pmid:12140307. doi:10146:55793-493.';
	}

	protected function getJournalArticleWithMoreThanSevenAuthorsResult() {
		return 'SILVA, V.A. et al. Etinobotânica Xucuru: espécies místicas. <i>Biotemas</i>, Florianópolis, v.15, n.1, p.45-57, jun 2000. pmid:12140307. doi:10146:55793-493.';
	}

	protected function getConfProcResult() {
		$this->markTestIncomplete();
	}
}
?>
