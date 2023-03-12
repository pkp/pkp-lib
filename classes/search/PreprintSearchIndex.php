<?php

/**
 * @file classes/search/PreprintSearchIndex.php
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

namespace APP\search;

use APP\core\Application;
use APP\facades\Repo;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\search\SearchFileParser;

use PKP\search\SubmissionSearch;
use PKP\search\SubmissionSearchIndex;
use PKP\submissionFile\SubmissionFile;

class PreprintSearchIndex extends SubmissionSearchIndex
{
    /**
     * @copydoc SubmissionSearchIndex::submissionMetadataChanged()
     */
    public function submissionMetadataChanged($submission)
    {
        // Check whether a search plug-in jumps in.
        $hookResult = Hook::call(
            'PreprintSearchIndex::preprintMetadataChanged',
            [$submission]
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
        $submissionId = $submission->getId();
        $this->_updateTextIndex($submissionId, SubmissionSearch::SUBMISSION_SEARCH_AUTHOR, $authorText);
        $this->_updateTextIndex($submissionId, SubmissionSearch::SUBMISSION_SEARCH_TITLE, $publication->getFullTitles());
        $this->_updateTextIndex($submissionId, SubmissionSearch::SUBMISSION_SEARCH_ABSTRACT, $publication->getData('abstract'));

        $this->_updateTextIndex($submissionId, SubmissionSearch::SUBMISSION_SEARCH_SUBJECT, (array) $this->_flattenLocalizedArray($publication->getData('subjects')));
        $this->_updateTextIndex($submissionId, SubmissionSearch::SUBMISSION_SEARCH_KEYWORD, (array) $this->_flattenLocalizedArray($publication->getData('keywords')));
        $this->_updateTextIndex($submissionId, SubmissionSearch::SUBMISSION_SEARCH_DISCIPLINE, (array) $this->_flattenLocalizedArray($publication->getData('disciplines')));
        $this->_updateTextIndex($submissionId, SubmissionSearch::SUBMISSION_SEARCH_TYPE, (array) $publication->getData('type'));
        $this->_updateTextIndex($submissionId, SubmissionSearch::SUBMISSION_SEARCH_COVERAGE, (array) $publication->getData('coverage'));
        // FIXME Index sponsors too?
    }

    /**
     * Delete keywords from the search index.
     *
     * @param int $preprintId
     * @param int $type optional
     * @param int $assocId optional
     */
    public function deleteTextIndex($preprintId, $type = null, $assocId = null)
    {
        $searchDao = DAORegistry::getDAO('PreprintSearchDAO');
        return $searchDao->deleteSubmissionKeywords($preprintId, $type, $assocId);
    }

    /**
     * Signal to the indexing back-end that an preprint file changed.
     *
     * @see PreprintSearchIndex::submissionMetadataChanged() above for more
     * comments.
     *
     * @param int $preprintId
     * @param int $type
     * @param SubmissionFile $submissionFile
     */
    public function submissionFileChanged($preprintId, $type, $submissionFile)
    {
        // Check whether a search plug-in jumps in.
        $hookResult = Hook::call(
            'PreprintSearchIndex::submissionFileChanged',
            [$preprintId, $type, $submissionFile->getId()]
        );

        // If no search plug-in is activated then fall back to the
        // default database search implementation.
        if ($hookResult === false || is_null($hookResult)) {
            $parser = SearchFileParser::fromFile($submissionFile);

            if (isset($parser) && $parser->open()) {
                $searchDao = DAORegistry::getDAO('PreprintSearchDAO');
                $objectId = $searchDao->insertObject($preprintId, $type, $submissionFile->getId());

                while (($text = $parser->read()) !== false) {
                    $this->_indexObjectKeywords($objectId, $text);
                }
                $parser->close();
            }
        }
    }

    /**
     * Remove indexed file contents for a submission
     *
     * @param Submission $submission
     */
    public function clearSubmissionFiles($submission)
    {
        $searchDao = DAORegistry::getDAO('PreprintSearchDAO');
        $searchDao->deleteSubmissionKeywords($submission->getId(), SubmissionSearch::SUBMISSION_SEARCH_GALLEY_FILE);
    }

    /**
     * Signal to the indexing back-end that all files (supplementary
     * and galley) assigned to an preprint changed and must be re-indexed.
     *
     * @see PreprintSearchIndex::submissionMetadataChanged() above for more
     * comments.
     *
     * @param Preprint $preprint
     */
    public function submissionFilesChanged($preprint)
    {
        // Check whether a search plug-in jumps in.
        $hookResult = Hook::call(
            'PreprintSearchIndex::submissionFilesChanged',
            [$preprint]
        );

        // If no search plug-in is activated then fall back to the
        // default database search implementation.
        if ($hookResult === false || is_null($hookResult)) {
            $submissionFiles = Repo::submissionFile()
                ->getCollector()
                ->filterBySubmissionIds([$preprint->getId()])
                ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_PROOF])
                ->getMany();

            foreach ($submissionFiles as $submissionFile) {
                $this->submissionFileChanged($preprint->getId(), SubmissionSearch::SUBMISSION_SEARCH_GALLEY_FILE, $submissionFile);

                $dependentFiles = Repo::submissionFile()
                    ->getCollector()
                    ->filterByAssoc(
                        Application::ASSOC_TYPE_SUBMISSION_FILE,
                        [$submissionFile->getId()]
                    )->filterBySubmissionIds([$preprint->getId()])
                    ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_DEPENDENT])
                    ->includeDependentFiles()
                    ->getMany();

                foreach ($dependentFiles as $dependentFile) {
                    $this->submissionFileChanged($preprint->getId(), SubmissionSearch::SUBMISSION_SEARCH_SUPPLEMENTARY_FILE, $dependentFile);
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
     * @param int $preprintId
     * @param int $type optional
     * @param int $assocId optional
     */
    public function submissionFileDeleted($preprintId, $type = null, $assocId = null)
    {
        // Check whether a search plug-in jumps in.
        $hookResult = Hook::call(
            'PreprintSearchIndex::submissionFileDeleted',
            [$preprintId, $type, $assocId]
        );

        // If no search plug-in is activated then fall back to the
        // default database search implementation.
        if ($hookResult === false || is_null($hookResult)) {
            $searchDao = DAORegistry::getDAO('PreprintSearchDAO'); /** @var PreprintSearchDAO $searchDao */
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
     * @param int $preprintId
     */
    public function preprintDeleted($preprintId)
    {
        // Trigger a hook to let the indexing back-end know that
        // an preprint was deleted.
        Hook::call(
            'PreprintSearchIndex::preprintDeleted',
            [$preprintId]
        );

        // The default indexing back-end does nothing when an
        // preprint is deleted (FIXME?).
    }

    /**
     * @copydoc SubmissionSearchIndex::submissionChangesFinished()
     */
    public function submissionChangesFinished()
    {
        // Trigger a hook to let the indexing back-end know that
        // the index may be updated.
        Hook::call(
            'PreprintSearchIndex::preprintChangesFinished'
        );

        // The default indexing back-end works completely synchronously
        // and will therefore not do anything here.
    }

    /**
     * @copydoc SubmissionSearchIndex::submissionChangesFinished()
     */
    public function preprintChangesFinished()
    {
        if (Config::getVar('debug', 'deprecation_warnings')) {
            trigger_error('Deprecated call to preprintChangesFinished. Use submissionChangesFinished instead.');
        }
        $this->submissionChangesFinished();
    }

    /**
     * Rebuild the search index for one or all servers.
     *
     * @param bool $log Whether to display status information
     *  to stdout.
     * @param Server $server If given the user wishes to
     *  re-index only one server. Not all search implementations
     *  may be able to do so. Most notably: The default SQL
     *  implementation does not support server-specific re-indexing
     *  as index data is not partitioned by server.
     * @param array $switches Optional index administration switches.
     */
    public function rebuildIndex($log = false, $server = null, $switches = [])
    {
        // Check whether a search plug-in jumps in.
        $hookResult = Hook::call(
            'PreprintSearchIndex::rebuildIndex',
            [$log, $server, $switches]
        );

        // If no search plug-in is activated then fall back to the
        // default database search implementation.
        if ($hookResult === false || is_null($hookResult)) {
            // Check that no server was given as we do
            // not support server-specific re-indexing.
            if (is_a($server, 'Server')) {
                exit(__('search.cli.rebuildIndex.indexingByServerNotSupported') . "\n");
            }

            // Clear index
            if ($log) {
                echo __('search.cli.rebuildIndex.clearingIndex') . ' ... ';
            }
            $searchDao = DAORegistry::getDAO('PreprintSearchDAO');
            $searchDao->clearIndex();
            if ($log) {
                echo __('search.cli.rebuildIndex.done') . "\n";
            }

            // Build index
            $serverDao = DAORegistry::getDAO('ServerDAO'); /** @var ServerDAO $serverDao */

            $servers = $serverDao->getAll();
            while ($server = $servers->next()) {
                $numIndexed = 0;

                if ($log) {
                    echo __('search.cli.rebuildIndex.indexing', ['serverName' => $server->getLocalizedName()]) . ' ... ';
                }

                $submissions = Repo::submission()
                    ->getCollector()
                    ->filterByContextIds([$server->getId()])
                    ->getMany();

                foreach ($submissions as $submission) {
                    if (!$submission->getSubmissionProgress()) { // Not incomplete
                        $this->submissionMetadataChanged($submission);
                        $this->submissionFilesChanged($submission);
                        $numIndexed++;
                    }
                }
                $this->submissionChangesFinished();

                if ($log) {
                    echo __('search.cli.rebuildIndex.result', ['numIndexed' => $numIndexed]) . "\n";
                }
            }
        }
    }


    //
    // Private helper methods
    //
    /**
     * Index a block of text for an object.
     *
     * @param int $objectId
     * @param string|array $text
     */
    protected function _indexObjectKeywords($objectId, $text)
    {
        $searchDao = DAORegistry::getDAO('PreprintSearchDAO');
        $keywords = $this->filterKeywords($text);
        $searchDao->insertObjectKeywords($objectId, $keywords);
    }

    /**
     * Add a block of text to the search index.
     *
     * @param int $preprintId
     * @param int $type
     * @param string $text
     * @param int $assocId optional
     */
    protected function _updateTextIndex($preprintId, $type, $text, $assocId = null)
    {
        $searchDao = DAORegistry::getDAO('PreprintSearchDAO');
        $objectId = $searchDao->insertObject($preprintId, $type, $assocId);
        $this->_indexObjectKeywords($objectId, $text);
    }

    /**
     * Flattens array of localized fields to a single, non-associative array of items
     *
     * @param array $arrayWithLocales Array of localized fields
     *
     * @return array
     */
    protected function _flattenLocalizedArray($arrayWithLocales)
    {
        $flattenedArray = [];
        foreach ($arrayWithLocales as $localeArray) {
            $flattenedArray = array_merge(
                $flattenedArray,
                $localeArray
            );
        }
        return $flattenedArray;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\search\PreprintSearchIndex', '\PreprintSearchIndex');
}
