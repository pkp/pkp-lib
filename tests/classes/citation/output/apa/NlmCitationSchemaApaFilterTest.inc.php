<?php

/**
 * @file tests/classes/citation/output/apa/NlmCitationSchemaApaFilterTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaApaFilterTest
 * @ingroup tests_classes_citation_output_apa
 * @see NlmCitationSchemaApaFilter
 *
 * @brief Tests for the NlmCitationSchemaApaFilter class.
 */

// $Id$

import('lib.pkp.classes.citation.output.apa.NlmCitationSchemaApaFilter');
import('lib.pkp.tests.classes.citation.output.NlmCitationSchemaCitationOutputFormatFilterTest');

class NlmCitationSchemaApaFilterTest extends NlmCitationSchemaCitationOutputFormatFilterTest {
	/*
	 * Implements abstract methods from NlmCitationSchemaCitationOutputFormatFilter
	 */
	protected function getFilterInstance() {
		return new NlmCitationSchemaApaFilter($this->getRequest());
	}

	protected function getBookResultNoAuthor() {
		return 'Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil (2001). São Paulo: Iglu.';
	}

	protected function getBookResult() {
		return 'Azevedo, M. A. (2001). <i>Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil</i>. São Paulo: Iglu.';
	}

	protected function getBookChapterResult() {
		return 'Azevedo, M. A., & Guerra, V. (2001) Psicologia genética e lógica. In: <i>Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil</i> (pp.15-25). São Paulo: Iglu.';
	}

	protected function getBookChapterWithEditorResult() {
		return 'Azevedo, M. A., & Guerra, V. (2001) Psicologia genética e lógica. In: L. Banks-Leite (Ed.), <i>Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil</i> (pp.15-25). São Paulo: Iglu.';
	}

	protected function getBookChapterWithEditorsResult() {
		return 'Azevedo, M. A., & Guerra, V. (2001) Psicologia genética e lógica. In: L. Banks-Leite & M. Velado, Jr. (Eds.), <i>Mania de bater: a punição corporal doméstica de crianças e adolescentes no Brasil</i> (pp.15-25). São Paulo: Iglu.';
	}

	protected function getJournalArticleResult() {
		return 'Silva, V. A., & dos Santos, P. (2000). Etinobotânica Xucuru: espécies místicas. <i>Biotemas, </i>15(1), 45-57. doi:10146:55793-493';
	}

	protected function getJournalArticleWithMoreThanSevenAuthorsResult() {
		return 'Silva, V. A., dos Santos, P., Miller, F. H., Choi, M. J., Angeli, L. L., Harland, A. A., . . . Thomas, S. T. (2000). Etinobotânica Xucuru: espécies místicas. <i>Biotemas, </i>15(1), 45-57. doi:10146:55793-493';
	}
}
?>
