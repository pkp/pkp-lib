<?php

/**
 * @file tests/config/CitationParserServiceTestCase.inc.php
 *
 * Copyright (c) 2003-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationParserServiceTestCase
 * @ingroup tests
 * @see RegexCitationParserServiceTest
 * @see ParscitCitationParserServiceTest
 * @see FreeciteCitationParserServiceTest
 * @see ParaciteCitationParserServiceTest
 *
 * @brief Base class for all CitationParserService implementation tests.
 */

// $Id$

import('tests.classes.citation.CitationServiceTestCase');

abstract class CitationParserServiceTestCase extends CitationServiceTestCase {
	/**
	 * This will call the currently tested parser for all
	 * raw citations contained in the file 'test-citations.txt'.
	 *
	 * It tests whether any of these citations
	 * triggers an error and creates a human readable and
	 * PHP parsable test result output so that the
	 * parser results can be checked (and improved) for all
	 * test citations.
	 *
	 * Setting the class constant TEST_ALL_CITATIONS to false
	 * will skip this test as it is very time consuming.
	 */
	public function testAllCitationsWithThisParser($parameters = array()) {
		// Is this test switched off?
		if (!self::TEST_ALL_CITATIONS) return;

		// Determine the test citation and result file names
		$sourceFile = dirname(__FILE__).DIRECTORY_SEPARATOR.'test-citations.txt';
		$parameterExtension = implore('', $parameters);
		$targetFile = dirname(dirname(dirname(dirname(__FILE__)))).DIRECTORY_SEPARATOR.
		              'results'.DIRECTORY_SEPARATOR.$this->getCitationServiceName().$parameterExtension.'Results.inc.php';

		// Get the test citations from the source file
		$testCitationsString = file_get_contents($sourceFile);
		$testCitations = explode("\n", $testCitationsString);

		// Instantiate the parser service
		$parserService =& $this->getCitationServiceInstance($parameters);

		// Start the output string as a parsable php file
		$resultString = '<?php'."\n".'$citationTestResults = array('."\n";

		foreach($testCitations as $rawCitationString) {
			// Call the parser service for every test citation
			$citation = new Citation(METADATA_GENRE_UNKNOWN, $rawCitationString);
			$parsedCitation =& $parserService->parse($citation);
			self::assertNotNull($parsedCitation);

			// Serialize the parsed citation
			$serializedParsedCitation = $this->serializeParsedCitation($parsedCitation);

			// Add the result to the output string
			$rawCitationOutput = str_replace("'", "\'", $rawCitationString);
			$resultString .= "\t'$rawCitationOutput' => \n".$serializedParsedCitation.",\n";
		}

		// Close the output string
		$resultString .= ");\n?>\n";

		// Write the results file
		file_put_contents($targetFile, $resultString);
	}

	private function serializeParsedCitation(Citation &$parsedCitation) {
		// Prepare transformation tables for the output serialization:
		// - the following lines will be deleted from our output file
		static $linesToDelete = array(
			'    [0-9]+ => ',
			'    array \(',
			'      \'_data\' => ',
			'    \),'
		);
		// - the genre integer IDs will be replaced with the corresponding
		//   constant names
		static $metadataGenres = array(
			METADATA_GENRE_UNKNOWN => 'METADATA_GENRE_UNKNOWN',
			METADATA_GENRE_BOOK => 'METADATA_GENRE_BOOK',
			METADATA_GENRE_BOOKCHAPTER => 'METADATA_GENRE_BOOKCHAPTER',
			METADATA_GENRE_JOURNAL => 'METADATA_GENRE_JOURNAL',
			METADATA_GENRE_JOURNALARTICLE => 'METADATA_GENRE_JOURNALARTICLE',
			METADATA_GENRE_CONFERENCE => 'METADATA_GENRE_CONFERENCE',
			METADATA_GENRE_CONFERENCEPROCEEDING => 'METADATA_GENRE_CONFERENCEPROCEEDING',
			METADATA_GENRE_DISSERTATION => 'METADATA_GENRE_DISSERTATION'
		);
		assert(count(Metadata::getSupportedGenres()) == count($metadataGenres));

		// Transform the result into an array that we can serialize
		// in a human-readable form and also re-import as PHP-parsable code.
		$parsedCitationArray = $parsedCitation->getNonEmptyElementsAsArray();
		if (isset($parsedCitationArray['authors'])) {
			foreach ($parsedCitationArray['authors'] as &$author) {
				$author = (array)$author;
			}
		}
		$parsedCitationOutput = var_export($parsedCitationArray, true);
		$parsedCitationOutputArray = explode("\n", $parsedCitationOutput);
		foreach($parsedCitationOutputArray as $key => &$parsedCitationOutputLine) {
			// Remove redundant author keys
			foreach($linesToDelete as $lineToDelete) {
				if (preg_match('/^'.$lineToDelete.'$/', $parsedCitationOutputLine)) {
					unset($parsedCitationOutputArray[$key]);
				}
			}

			// Insert meta-data genre constants
			$matches = array();
			if (preg_match('/^  \'genre\' => (?P<genre>[0-9]+),$/', $parsedCitationOutputLine, $matches)) {
				assert(isset($metadataGenres[(int)$matches['genre']]));
				$parsedCitationOutputLine = '  \'genre\' => '.$metadataGenres[(int)$matches['genre']].',';
			}

			// Correctly indent the output line
			$parsedCitationOutputLine = "\t\t\t".preg_replace('/^\t\t\t/', "\t\t", str_replace('  ', "\t", $parsedCitationOutputLine));
		}

		// Create the final serialized format
		return implode("\n", $parsedCitationOutputArray);
	}
}
?>
