<?php

/**
 * @file classes/search/PreprintSearchIndex.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintSearchIndex
 * @ingroup search
 *
 * @brief Class to maintain the preprint search index.
 */

import('lib.pkp.classes.search.SubmissionSearchIndex');

class PreprintSearchIndex extends SubmissionSearchIndex {

	/**
	 * @copydoc SubmissionSearchIndex::submissionMetadataChanged()
	 */
	public function submissionMetadataChanged($submission) {
		// Check whether a search plug-in jumps in.
		$hookResult = HookRegistry::call(
			'PreprintSearchIndex::preprintMetadataChanged',
			array($submission)
		);

		if (!empty($hookResult)) {
			return;
		}

		$publication = $submission->getCurrentPublication();

		// Build author keywords
		$authorText = [];
		foreach ($publication->getData('authors') as $author) {
			$authorText = array_merge(
				$authorText,
				array_values((array) $author->getData('givenName')),
				array_values((array) $author->getData('familyName')),
				array_values((array) $author->getData('preferredPublicName')),
				array_values(array_map('strip_tags', (array) $author->getData('affiliation'))),
				array_values(array_map('strip_tags', (array) $author->getData('biography')))
			);
		}

		// Update search index
		import('classes.search.PreprintSearch');
		$submissionId = $submission->getId();
		$this->_updateTextIndex($submissionId, SUBMISSION_SEARCH_AUTHOR, $authorText);
		$this->_updateTextIndex($submissionId, SUBMISSION_SEARCH_TITLE, $publication->getFullTitles());
		$this->_updateTextIndex($submissionId, SUBMISSION_SEARCH_ABSTRACT, $publication->getData('abstract'));

		$this->_updateTextIndex($submissionId, SUBMISSION_SEARCH_SUBJECT, (array) $this->_flattenLocalizedArray($publication->getData('subjects')));
		$this->_updateTextIndex($submissionId, SUBMISSION_SEARCH_KEYWORD, (array) $this->_flattenLocalizedArray($publication->getData('keywords')));
		$this->_updateTextIndex($submissionId, SUBMISSION_SEARCH_DISCIPLINE, (array) $this->_flattenLocalizedArray($publication->getData('disciplines')));
		$this->_updateTextIndex($submissionId, SUBMISSION_SEARCH_TYPE, (array) $publication->getData('type'));
		$this->_updateTextIndex($submissionId, SUBMISSION_SEARCH_COVERAGE, (array) $publication->getData('coverage'));
		// FIXME Index sponsors too?
	}

	/**
	 * Delete keywords from the search index.
	 * @param $preprintId int
	 * @param $type int optional
	 * @param $assocId int optional
	 */
	public function deleteTextIndex($preprintId, $type = null, $assocId = null) {
		$searchDao = DAORegistry::getDAO('PreprintSearchDAO');
		return $searchDao->deleteSubmissionKeywords($preprintId, $type, $assocId);
	}

	/**
	 * Signal to the indexing back-end that an preprint file changed.
	 *
	 * @see PreprintSearchIndex::submissionMetadataChanged() above for more
	 * comments.
	 *
	 * @param $preprintId int
	 * @param $type int
	 * @param $submissionFile SubmissionFile
	 */
	public function submissionFileChanged($preprintId, $type, $submissionFile) {
		// Check whether a search plug-in jumps in.
		$hookResult = HookRegistry::call(
			'PreprintSearchIndex::submissionFileChanged',
			array($preprintId, $type, $submissionFile->getId())
		);

		// If no search plug-in is activated then fall back to the
		// default database search implementation.
		if ($hookResult === false || is_null($hookResult)) {
			$parser = SearchFileParser::fromFile($submissionFile);

			if (isset($parser) && $parser->open()) {
				$searchDao = DAORegistry::getDAO('PreprintSearchDAO');
				$objectId = $searchDao->insertObject($preprintId, $type, $submissionFile->getId());

				$position = 0;
				while(($text = $parser->read()) !== false) {
					$this->_indexObjectKeywords($objectId, $text, $position);
				}
				$parser->close();
			}
		}
	}

	/**
	 * Remove indexed file contents for a submission
	 * @param $submission Submission
	 */
	public function clearSubmissionFiles($submission) {
		$searchDao = DAORegistry::getDAO('PreprintSearchDAO');
		$searchDao->deleteSubmissionKeywords($submission->getId(), SUBMISSION_SEARCH_GALLEY_FILE);
	}

	/**
	 * Signal to the indexing back-end that all files (supplementary
	 * and galley) assigned to an preprint changed and must be re-indexed.
	 *
	 * @see PreprintSearchIndex::submissionMetadataChanged() above for more
	 * comments.
	 *
	 * @param $preprint Preprint
	 */
	public function submissionFilesChanged($preprint) {
		// Check whether a search plug-in jumps in.
		$hookResult = HookRegistry::call(
			'PreprintSearchIndex::submissionFilesChanged',
			array($preprint)
		);

		// If no search plug-in is activated then fall back to the
		// default database search implementation.
		if ($hookResult === false || is_null($hookResult)) {
			import('lib.pkp.classes.submission.SubmissionFile'); // Constants
			$submissionFilesIterator = Services::get('submissionFile')->getMany([
				'submissionIds' => [$preprint->getId()],
				'fileStages' => [SUBMISSION_FILE_PROOF],
			]);
			foreach ($submissionFilesIterator as $submissionFile) {
				$this->submissionFileChanged($preprint->getId(), SUBMISSION_SEARCH_GALLEY_FILE, $submissionFile);
				$dependentFilesIterator = Services::get('submissionFile')->getMany([
					'assocTypes' => [ASSOC_TYPE_SUBMISSION_FILE],
					'assocIds' => [$submissionFile->getId()],
					'submissionIds' => [$preprint->getId()],
					'fileStages' => [SUBMISSION_FILE_DEPENDENT],
					'includeDependentFiles' => true,
				]);
				foreach ($dependentFilesIterator as $dependentFile) {
					$this->submissionFileChanged($preprint->getId(), SUBMISSION_SEARCH_SUPPLEMENTARY_FILE, $dependentFile);
				}
			}
		}
	}

	/**
	 * Signal to the indexing back-end that a file was deleted.
	 *
	 * @see PreprintSearchIndex::submissionMetadataChanged() above for more
	 * comments.
	 *
	 * @param $preprintId int
	 * @param $type int optional
	 * @param $assocId int optional
	 */
	public function submissionFileDeleted($preprintId, $type = null, $assocId = null) {
		// Check whether a search plug-in jumps in.
		$hookResult = HookRegistry::call(
			'PreprintSearchIndex::submissionFileDeleted',
			array($preprintId, $type, $assocId)
		);

		// If no search plug-in is activated then fall back to the
		// default database search implementation.
		if ($hookResult === false || is_null($hookResult)) {
			$searchDao = DAORegistry::getDAO('PreprintSearchDAO'); /* @var $searchDao PreprintSearchDAO */
			return $searchDao->deleteSubmissionKeywords($preprintId, $type, $assocId);
		}
	}

	/**
	 * Signal to the indexing back-end that the metadata of
	 * a supplementary file changed.
	 *
	 * @see PreprintSearchIndex::submissionMetadataChanged() above for more
	 * comments.
	 *
	 * @param $preprintId integer
	 */
	public function preprintDeleted($preprintId) {
		// Trigger a hook to let the indexing back-end know that
		// an preprint was deleted.
		HookRegistry::call(
			'PreprintSearchIndex::preprintDeleted',
			array($preprintId)
		);

		// The default indexing back-end does nothing when an
		// preprint is deleted (FIXME?).
	}

	/**
	 * @copydoc SubmissionSearchIndex::submissionChangesFinished()
	 */
	public function submissionChangesFinished() {
		// Trigger a hook to let the indexing back-end know that
		// the index may be updated.
		HookRegistry::call(
			'PreprintSearchIndex::preprintChangesFinished'
		);

		// The default indexing back-end works completely synchronously
		// and will therefore not do anything here.
	}

	/**
	 * @copydoc SubmissionSearchIndex::submissionChangesFinished()
	 */
	public function preprintChangesFinished() {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated call to preprintChangesFinished. Use submissionChangesFinished instead.');
		$this->submissionChangesFinished();
	}

	/**
	 * Rebuild the search index for one or all servers.
	 * @param $log boolean Whether to display status information
	 *  to stdout.
	 * @param $server Server If given the user wishes to
	 *  re-index only one server. Not all search implementations
	 *  may be able to do so. Most notably: The default SQL
	 *  implementation does not support server-specific re-indexing
	 *  as index data is not partitioned by server.
	 * @param $switches array Optional index administration switches.
	 */
	public function rebuildIndex($log = false, $server = null, $switches = array()) {
		// Check whether a search plug-in jumps in.
		$hookResult = HookRegistry::call(
			'PreprintSearchIndex::rebuildIndex',
			array($log, $server, $switches)
		);

		// If no search plug-in is activated then fall back to the
		// default database search implementation.
		if ($hookResult === false || is_null($hookResult)) {

			AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);

			// Check that no server was given as we do
			// not support server-specific re-indexing.
			if (is_a($server, 'Server')) die(__('search.cli.rebuildIndex.indexingByServerNotSupported') . "\n");

			// Clear index
			if ($log) echo __('search.cli.rebuildIndex.clearingIndex') . ' ... ';
			$searchDao = DAORegistry::getDAO('PreprintSearchDAO');
			$searchDao->clearIndex();
			if ($log) echo __('search.cli.rebuildIndex.done') . "\n";

			// Build index
			$serverDao = DAORegistry::getDAO('ServerDAO'); /* @var $serverDao ServerDAO */

			$servers = $serverDao->getAll();
			while ($server = $servers->next()) {
				$numIndexed = 0;

				if ($log) echo __('search.cli.rebuildIndex.indexing', array('serverName' => $server->getLocalizedName())) . ' ... ';

				$submissionsIterator = Services::get('submission')->getMany(['contextId' => $server->getId()]);
				foreach ($submissionsIterator as $submission) {
					if ($submission->getSubmissionProgress() == 0) { // Not incomplete
						$this->submissionMetadataChanged($submission);
						$this->submissionFilesChanged($submission);
						$numIndexed++;
					}
				}
				$this->submissionChangesFinished();

				if ($log) echo __('search.cli.rebuildIndex.result', array('numIndexed' => $numIndexed)) . "\n";
			}
		}
	}


	//
	// Private helper methods
	//
	/**
	 * Index a block of text for an object.
	 * @param $objectId int
	 * @param $text string
	 * @param $position int
	 */
	protected function _indexObjectKeywords($objectId, $text, &$position) {
		$searchDao = DAORegistry::getDAO('PreprintSearchDAO');
		$keywords = $this->filterKeywords($text);
		for ($i = 0, $count = count($keywords); $i < $count; $i++) {
			if ($searchDao->insertObjectKeyword($objectId, $keywords[$i], $position) !== null) {
				$position += 1;
			}
		}
	}

	/**
	 * Add a block of text to the search index.
	 * @param $preprintId int
	 * @param $type int
	 * @param $text string
	 * @param $assocId int optional
	 */
	protected function _updateTextIndex($preprintId, $type, $text, $assocId = null) {
		$searchDao = DAORegistry::getDAO('PreprintSearchDAO');
		$objectId = $searchDao->insertObject($preprintId, $type, $assocId);
		$position = 0;
		$this->_indexObjectKeywords($objectId, $text, $position);
	}

	/**
	 * Flattens array of localized fields to a single, non-associative array of items
	 *
	 * @param $arrayWithLocales array Array of localized fields
	 * @return array
	 */
	protected function _flattenLocalizedArray($arrayWithLocales) {
		$flattenedArray = array();
		foreach ($arrayWithLocales as $localeArray) {
			$flattenedArray = array_merge(
				$flattenedArray,
				$localeArray
			);
		}
		return $flattenedArray;
	}
}


