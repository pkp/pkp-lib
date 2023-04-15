<?php
/**
 * @file classes/decision/steps/PromoteFiles.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PromoteFiles
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
    /** @var int[] */
    public array $selected = [];
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
     *
     * @param bool $selectedByDefault Whether the files in this list should be selected by default
     */
    public function addFileList(string $name, Collector $collector, bool $selectedByDefault = true): self
    {
        $files = $collector->getMany();

        $fileSummaries = Repo::submissionFile()
            ->getSchemaMap()
            ->summarizeMany($files, $this->genres);

        $this->lists[] = [
            'name' => $name,
            'files' => $fileSummaries->values(),
        ];

        if ($selectedByDefault && $files->count()) {
            $this->selected = array_merge(
                $this->selected,
                $files->map(fn ($file) => $file->getId())->all()
            );
        }

        return $this;
    }

    public function getState(): stdClass
    {
        $config = parent::getState();
        $config->to = $this->to;
        $config->selected = $this->selected;
        $config->lists = $this->lists;

        return $config;
    }
}
