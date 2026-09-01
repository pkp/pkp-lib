<?php

declare(strict_types=1);

/**
 * @file jobs/submissions/UpdateSubmissionSearchJob.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateSubmissionSearchJob
 *
 * @ingroup jobs
 *
 * @brief Class to handle the Submission Search data update as a Job for the database engine
 */

namespace PKP\jobs\submissions;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\jobs\BaseJob;
use PKP\search\engines\DatabaseEngine;
use PKP\search\parsers\SearchFileParser;
use PKP\submissionFile\SubmissionFile;

class UpdateSubmissionSearchJob extends BaseJob implements \PKP\queue\ContextAwareJob
{
    /**
     * The maximum number of SECONDS a job should get processed before consider failed
     */
    public int $timeout = 180;

    /**
     * The submission ID
     */
    protected int $submissionId;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(int $submissionId)
    {
        parent::__construct();

        $this->submissionId = $submissionId;
    }

    /**
     * Get the context ID for this job.
     */
    public function getContextId(): int
    {
        return Repo::submission()->get($this->submissionId)->getData('contextId');
    }

    /**
     * Execute the job.
     *
     */
    public function handle(): void
    {
        $submission = Repo::submission()->get($this->submissionId);
        $submission->getData('publications')->each(function ($publication) use ($submission) {
            $titles = (array) $publication->getFullTitles();
            $abstracts = (array) $publication->getData('abstract');
            $bodies = [];
            $authors = [];

            // Index all galleys
            $submissionFiles = Repo::submissionFile()
                ->getCollector()
                ->filterByAssoc(Application::ASSOC_TYPE_REPRESENTATION, [$publication->getId()])
                ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_PROOF])
                ->getMany();

            foreach ($submissionFiles as $submissionFile) {
                $galley = Application::getRepresentationDAO()->getById($submissionFile->getData('assocId'));
                $parser = SearchFileParser::fromFile($submissionFile);
                if (!$parser) {
                    continue;
                }
                try {
                    $parser->open();
                    do {
                        for ($buffer = ''; ($chunk = $parser->read()) !== false && strlen($buffer .= $chunk) < DatabaseEngine::MINIMUM_DATA_LENGTH;);
                        if (strlen($buffer)) {
                            $bodies[$galley->getLocale()] = ($bodies[$galley->getLocale()] ?? '') . $buffer;
                        }
                    } while ($chunk !== false);
                } catch (\Throwable $e) {
                    error_log($e->getMessage());
                } finally {
                    $parser->close();
                }
            }

            // Index all authors
            foreach ($publication->getData('authors') as $author) {
                foreach ($author->getFullNames() as $locale => $fullName) {
                    $authors[$locale] = ($authors[$locale] ?? '') . $fullName . ' ';
                }
            }

            $locales = array_unique(array_merge(array_keys($titles), array_keys($abstracts), array_keys($bodies), array_keys($authors)));

            foreach ($locales as $locale) {
                DB::table('submissions_fulltext')->upsert(
                    [
                        'submission_id' => $submission->getId(),
                        'publication_id' => $publication->getId(),
                        'locale' => $locale,
                        'title' => $titles[$locale] ?? '',
                        'abstract' => $abstracts[$locale] ?? '',
                        'body' => $bodies[$locale] ?? '',
                        'authors' => $authors[$locale] ?? '',
                    ],
                    ['submission_id', 'publication_id', 'locale'],
                    ['title', 'abstract', 'body']
                );
            }
        });
    }
}
