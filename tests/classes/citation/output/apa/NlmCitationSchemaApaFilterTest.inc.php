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
		return '<p style="text-indent:-2em;margin-left:2em">Mania de bater: A punição corporal doméstica de crianças e adolescentes no Brasil (2001). São Paulo: Iglu. <a href="http://scholar.google.com/scholar?ie=UTF-8&oe=UTF-8&hl=en&q=%22Mania%20de%20bater%3A%20a%20puni%C3%A7%C3%A3o%20corporal%20dom%C3%A9stica%20de%20crian%C3%A7as%20e%20adolescentes%20no%20Brasil%22+" target="_blank">[Google Scholar]</a></p>';
	}

	protected function getBookResult() {
		return '<p style="text-indent:-2em;margin-left:2em">Azevedo, M. A. (2001). <i>Mania de bater: A punição corporal doméstica de crianças e adolescentes no Brasil</i>. São Paulo: Iglu. <a href="http://scholar.google.com/scholar?ie=UTF-8&oe=UTF-8&hl=en&q=author:%22Azevedo%22+%22Mania%20de%20bater%3A%20a%20puni%C3%A7%C3%A3o%20corporal%20dom%C3%A9stica%20de%20crian%C3%A7as%20e%20adolescentes%20no%20Brasil%22+" target="_blank">[Google Scholar]</a></p>';
	}

	protected function getBookChapterResult() {
		return '<p style="text-indent:-2em;margin-left:2em">Azevedo, M. A., & Guerra, V. (2001) Psicologia genética e lógica. In <i>Mania de bater: A punição corporal doméstica de crianças e adolescentes no Brasil</i> (pp. 15-25). São Paulo: Iglu. <a href="http://scholar.google.com/scholar?ie=UTF-8&oe=UTF-8&hl=en&q=author:%22Azevedo%22+%22Mania%20de%20bater%3A%20a%20puni%C3%A7%C3%A3o%20corporal%20dom%C3%A9stica%20de%20crian%C3%A7as%20e%20adolescentes%20no%20Brasil%22+" target="_blank">[Google Scholar]</a></p>';
	}

	protected function getBookChapterWithEditorResult() {
		return '<p style="text-indent:-2em;margin-left:2em">Azevedo, M. A., & Guerra, V. (2001) Psicologia genética e lógica. In L. Banks-Leite (Ed.), <i>Mania de bater: A punição corporal doméstica de crianças e adolescentes no Brasil</i> (pp. 15-25). São Paulo: Iglu. <a href="http://scholar.google.com/scholar?ie=UTF-8&oe=UTF-8&hl=en&q=author:%22Azevedo%22+%22Mania%20de%20bater%3A%20a%20puni%C3%A7%C3%A3o%20corporal%20dom%C3%A9stica%20de%20crian%C3%A7as%20e%20adolescentes%20no%20Brasil%22+" target="_blank">[Google Scholar]</a></p>';
	}

	protected function getBookChapterWithEditorsResult() {
		return '<p style="text-indent:-2em;margin-left:2em">Azevedo, M. A., & Guerra, V. (2001) Psicologia genética e lógica. In L. Banks-Leite & M. Velado, Jr. (Eds.), <i>Mania de bater: A punição corporal doméstica de crianças e adolescentes no Brasil</i> (pp. 15-25). São Paulo: Iglu. <a href="http://scholar.google.com/scholar?ie=UTF-8&oe=UTF-8&hl=en&q=author:%22Azevedo%22+%22Mania%20de%20bater%3A%20a%20puni%C3%A7%C3%A3o%20corporal%20dom%C3%A9stica%20de%20crian%C3%A7as%20e%20adolescentes%20no%20Brasil%22+" target="_blank">[Google Scholar]</a></p>';
	}

	protected function getJournalArticleResult() {
		return '<p style="text-indent:-2em;margin-left:2em">Silva, V. A., & dos Santos, P. (2000). Etinobotânica Xucuru: espécies místicas. <i>Biotemas, </i>15(1), 45-57. doi:10146:55793-493 <a href="http://scholar.google.com/scholar?ie=UTF-8&oe=UTF-8&hl=en&q=author:%22Silva%22+%22Biotemas%22+Etinobot%C3%A2nica%20Xucuru%3A%20esp%C3%A9cies%20m%C3%ADsticas+10146%3A55793-493" target="_blank">[Google Scholar]</a></p>';
	}

	protected function getJournalArticleWithMoreThanSevenAuthorsResult() {
		return '<p style="text-indent:-2em;margin-left:2em">Silva, V. A., dos Santos, P., Miller, F. H., Choi, M. J., Angeli, L. L., Harland, A. A., . . . Thomas, S. T. (2000). Etinobotânica Xucuru: espécies místicas. <i>Biotemas, </i>15(1), 45-57. doi:10146:55793-493 <a href="http://scholar.google.com/scholar?ie=UTF-8&oe=UTF-8&hl=en&q=author:%22Silva%22+%22Biotemas%22+Etinobot%C3%A2nica%20Xucuru%3A%20esp%C3%A9cies%20m%C3%ADsticas+10146%3A55793-493" target="_blank">[Google Scholar]</a></p>';
	}
}
?>
