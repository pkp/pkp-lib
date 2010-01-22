<?php

/**
 * @file classes/citation/RegexCitationParserService.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RegexCitationParserService
 * @ingroup citation
 * @see CitationMangager
 * @see CitationParserService
 *
 * @brief A simple regex based citation parsing service. Uses regexes to break a
 *        citation string into metadata elements. Works best on ICMJE/Vancouver-type
 *        journal citations.
 */

// $Id$

import('citation.CitationParserService');

class RegexCitationParserService extends CitationParserService {
	/**
	 * @see CitationParserService::parseInternal()
	 * @param $citationString string
	 * @param $citation Citation
	 */
	function parseInternal($citationString, &$citation) {
		// Initialize the parser result array
		$matches = array();
		
		// Parse out any embedded URLs
		$urlPattern = '(<?(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.,]*(\?[^\s>]+)?)?)?)>?)';
		if (String::regexp_match_get($urlPattern, $citationString, $matches)) {
			// Assume that the URL is a link to the resource.
			$citation->setUrl($matches[1]);
			
			// Remove the URL from the citation string
			$citationString = String::regexp_replace($urlPattern, '', $citationString);
			
			// If the URL is a link to PubMed, save the PMID
			$pmIdExpressions = array(
				'/list_uids=(?P<pmId>\d+)/i',
				'/pubmed.*details_term=(?P<pmId>\d+)/i',
				'/pubmedid=(?P<pmId>\d+)/i'
			);
			foreach ($pmIdExpressions as $pmIdExpression) {
				if (String::regexp_match_get($pmIdExpression, $matches[1], $pmIdMatches) ) {
					$citation->setPmId($pmIdMatches['pmId']);
					break;
				}
			}
		}

		// Parse out an embedded PMID and remove from the citation string
		$pmidPattern = '/pmid:?\s*(\d+)/i';
		if (String::regexp_match_get($pmidPattern, $citationString, $matches) ) {
			$citation->setPmId($matches[1]);
			$citationString = String::regexp_replace($pmidPattern, '', $citationString);
		}

		// Parse out an embedded DOI and remove it from the citation string
		$doiPattern = '/doi:?\s*(\S+)/i';
		if (String::regexp_match_get($doiPattern, $citationString, $matches) ) {
			$citation->setDOI($matches[1]);
			$citationString = String::regexp_replace($doiPattern, '', $citationString);
		}

		// Parse out the access date if we have one and remove it from the citation string
		$accessDatePattern = '/accessed:?\s*([\s\w]+)/i';
		if (String::regexp_match_get($accessDatePattern, $citationString, $matches)) {
			$citation->setAccessDate($this->normalizeDateString($matches[1]));
			$citationString = String::regexp_replace($accessDatePattern, '', $citationString );
		}

		// Clean out square brackets
		$citationString = String::regexp_replace('/\[(\s*(pubmed|medline|full text)\s*)*]/i', '', $citationString);

		// Book citation
		$unparsedTail = '';
		if (String::regexp_match_get("/\s*(?P<authors>[^\.]+)\.\s*(?P<bookTitle>.*?)\s*(?P<place>[^\.]*):\s*(?P<publisher>[^:]*?);\s*(?P<issuedDate>\d\d\d\d.*?)(?P<tail>.*)/", $citationString, $matches)) {
			$citation->setGenre(METADATA_GENRE_BOOK);
			$citation->setAuthors($this->parseAuthorsString($matches['authors']));
			$citation->setBookTitle($matches['bookTitle']);
			$citation->setPlace($matches['place']);
			$citation->setPublisher($matches['publisher']);
			$citation->setIssuedDate($this->normalizeDateString($matches['issuedDate']));
			$unparsedTail = $matches['tail'];

		// Journal citation
		} elseif (String::regexp_match_get("/\s*(?P<authors>[^\.]+)\.\s*(?P<titleSource>.*)\s*(?P<issuedDate>\d\d\d\d.*?);(?P<volumeAndIssue>[^:]+):(?P<tail>.*)/", $citationString, $matches)) {
			$citation->setGenre(METADATA_GENRE_JOURNALARTICLE);
			$citation->setAuthors($this->parseAuthorsString($matches['authors']));
			
			$titleSource = array();
			if (String::regexp_match_get("/(.*[\.!\?])(.*)/", trim($matches['titleSource'], " ."), $titleSource)) {
				$citation->setArticleTitle($titleSource[1]);
				$citation->setJournalTitle($titleSource[2]);
			}
			$citation->setIssuedDate($this->normalizeDateString($matches['issuedDate']));

			$volumeAndIssue = array();
			if (String::regexp_match_get("/([^\(]+)(\(([^\)]+)\))?/", $matches['volumeAndIssue'], $volumeAndIssue)) {
				$citation->setVolume($volumeAndIssue[1]);
				if (isset($volumeAndIssue[3])) $citation->setIssue($volumeAndIssue[3]);
			}

			$unparsedTail = $matches['tail'];

		// Web citation with or without authors
		} elseif (String::regexp_match_get("/\s*(?P<citationSource>.*?)\s*URL:\s*(?P<tail>.*)/", $citationString, $matches)) {
			$citation->setGenre(METADATA_GENRE_UNKNOWN);
			$unparsedTail = $matches['tail'];
			
			$citationParts = explode(".", trim($matches['citationSource'], '. '));
			switch (count($citationParts)) {
				case 0:
					// This case should never occur...
					assert(false);
					break;
					
				case 1:
					// Assume this to be a title for the web site.
					$citation->setArticleTitle($citationParts[0]);
					break;
					
				case 2:
					// Assume the format: Authors. Title.
					$citation->setAuthors($this->parseAuthorsString($citationParts[0]));
					$citation->setArticleTitle($citationParts[1]);
					break;
					
				default:
					// Assume the format: Authors. Article Title. Journal Title.
					$citation->setAuthors($this->parseAuthorsString(array_shift($citationParts)));
					// The last part is assumed to be the journal title
					$citation->setJournalTitle(array_pop($citationParts));
					// Everything in between is assumed to belong to the article title
					$citation->setArticleTitle(implode('.', $citationParts));
			}
		}

		// TODO: Handle in-ref titles, eg. with editor lists

		// Extract page numbers if possible
		$pagesPattern = "/^[:p\.\s]*(?P<firstPage>[Ee]?\d+)(-(?P<lastPage>\d+))?/";
		if (!empty($unparsedTail) && String::regexp_match_get($pagesPattern, $unparsedTail, $matches)) {
			$citation->setFirstPage($matches['firstPage']);
			if (isset($matches['lastPage'])) $citation->setLastPage($matches['lastPage']);
			
			// Add the unparsed part of the citation string as a comment so it doesn't get lost.
			$comment = $this->trimPunctuation(String::regexp_replace($pagesPattern, '', $unparsedTail));
			if (!empty($comment)) $citation->addComment($comment);
		}
	}
}
?>