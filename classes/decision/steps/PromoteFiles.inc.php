<?php
/**
 * @file classes/decision/steps/PromoteFiles.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class decision
 *
 * @brief A step in an editorial decision workflow that allows the editor to
 *   copy files from one or more file stages to a new stage.
 */

namespace PKP\decision\steps;

use APP\facades\Repo;
use APP\submission\Submission;
use PKP\decision\Step;
use PKP\submission\Genre;
use PKP\submissionFile\Collector;
use stdClass;

class PromoteFiles extends Step
{
    public string $type = 'promoteFiles';
    public int $to;
    public array $lists = [];
    public Submission $submission;

    /** @var Genre[] $genres File genres in this context */
    public array $genres = [];

    /**
     * @param integer $to Selected files are copied to this file stage
     * @param Genre[] $genres File genres in this context
     */
    public function __construct(string $id, string $name, string $description, int $to, Submission $submission, array $genres)
    {
        parent::__construct($id, $name, $description);
        $this->submission = $submission;
        $this->to = $to;

        $this->genres = $genres;
    }

    /**
     * Add a list of files that can be copied to the next stage
     */
    public function addFileList(string $name, Collector $collector): self
    {
        $files = Repo::submissionFile()
            ->getSchemaMap()
            ->summarizeMany(Repo::submissionFile()->getMany($collector), $this->genres);

        $this->lists[] = [
            'name' => $name,
            'files' => $files->values(),
        ];

        return $this;
    }

    public function getState(): stdClass
    {
        $config = parent::getState();
        $config->to = $this->to;
        $config->selected = [];
        $config->lists = $this->lists;

        return $config;
    }
}
