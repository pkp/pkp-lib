<?php
/**
 * @file classes/submissionFile/Collector.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submissionFile
 *
 * @brief A helper class to configure a Query Builder to get a collection of submission files
 */

namespace PKP\submissionFile;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\HookRegistry;

class Collector implements CollectorInterface
{
    /** @var DAO */
    public $dao;

    /** @var array get submission files for one or more file stages */
    protected $fileStages = [];

    /** @var array get submission files for one or more genres */
    protected $genreIds = [];

    /** @var array get submission files for one or more review rounds */
    protected $reviewRoundIds = [];

    /** @var array get submission files for one or more review assignments */
    protected $reviewIds = [];

    /** @var array get submission files for one or more submissions */
    protected $submissionIds = [];

    /** @var array get submission files matching one or more files */
    protected $fileIds = [];

    /** @var array get submission files matching one or more ASSOC_TYPE */
    protected $assocTypes = [];

    /** @var array get submission files matching an ASSOC_ID with one of the assocTypes */
    protected $assocIds = [];

    /** @var boolean include submission files in the SUBMISSION_FILE_DEPENDENT stage */
    protected $includeDependentFiles = false;

    /** @var int */
    public $count;

    /** @var int */
    public $offset;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /**
     * Set fileStages filter
     */
    public function filterByFileStages(array $fileStages): self
    {
        $this->fileStages = $fileStages;

        return $this;
    }

    /**
     * Set genreIds filter
     */
    public function filterByGenreIds(array $genreIds): self
    {
        $this->genreIds = $genreIds;

        return $this;
    }

    /**
     * Set review rounds filter
     */
    public function filterByReviewRoundIds(array $reviewRoundIds): self
    {
        $this->reviewRoundIds = $reviewRoundIds;

        return $this;
    }

    /**
     * Set review assignments filter
     */
    public function filterByReviewIds(array $reviewIds): self
    {
        $this->reviewIds = $reviewIds;

        return $this;
    }

    /**
     * Set submissionIds filter
     */
    public function filterBySubmissionIds(array $submissionIds): self
    {
        $this->submissionIds = $submissionIds;

        return $this;
    }

    /**
     * Set fileIds filter
     */
    public function filterByFileIds(array $fileIds): self
    {
        $this->fileIds = $fileIds;

        return $this;
    }

    /**
     * Set assocType and assocId filters
     *
     * @param array $assocTypes One or more of the ASSOC_TYPE_ constants
     * @param array $assocIds Match with ids for these assoc types
     */
    public function filterByAssoc(array $assocTypes, array $assocIds = []): self
    {
        $this->assocTypes = $assocTypes;

        if ($assocIds !== []) {
            $this->assocIds = $assocIds;
        }

        return $this;
    }

    /**
     * Set uploaderUserIds filter
     */
    public function filterByUploaderUserIds(array $uploaderUserIds): self
    {
        $this->uploaderUserIds = $uploaderUserIds;

        return $this;
    }

    /**
     * Whether or not to include dependent files in the results
     */
    public function filterByIncludeDependentFiles(bool $includeDependentFiles): self
    {
        $this->includeDependentFiles = $includeDependentFiles;

        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $qb = DB::table($this->dao->table . ' as sf');

        if ($this->submissionIds !== []) {
            $qb->whereIn('sf.submission_id', $this->submissionIds);
        }

        if ($this->fileStages !== []) {
            $qb->whereIn('sf.file_stage', $this->fileStages);
        }

        if ($this->genreIds !== []) {
            $qb->whereIn('sf.genre_id', $this->genreIds);
        }

        if ($this->fileIds !== []) {
            $qb->leftJoin('submission_file_revisions as sfr', 'sfr.submission_file_id', '=', 'sf.submission_file_id')
                ->whereIn('sfr.file_id', $this->fileIds);
        }

        if ($this->reviewRoundIds !== []) {
            $qb->join('review_round_files as rr', 'rr.submission_file_id', '=', 'sf.submission_file_id')
                ->whereIn('rr.review_round_id', $this->reviewRoundIds);
        }

        if ($this->reviewIds !== []) {
            $qb->join('review_files as rf', 'rf.submission_file_id', '=', 'sf.submission_file_id')
                ->whereIn('rf.review_id', $this->reviewIds);
        }

        if ($this->assocTypes !== []) {
            $qb->whereIn('sf.assoc_type', $this->assocTypes);

            if ($this->assocIds !== []) {
                $qb->whereIn('sf.assoc_id', $this->assocIds);
            }
        }

        if ($this->uploaderUserIds !== []) {
            $qb->whereIn('sf.uploader_user_id', $this->uploaderUserIds);
        }

        if (empty($this->includeDependentFiles) && !in_array(SubmissionFile::SUBMISSION_FILE_DEPENDENT, $this->fileStages)) {
            $qb->where('sf.file_stage', '!=', SubmissionFile::SUBMISSION_FILE_DEPENDENT);
        }

        $qb->orderBy('sf.created_at', 'desc');
        $qb->groupBy('sf.submission_id');

        if ($this->count > 0) {
            $qb->limit($this->count);
        }

        if ($this->offset > 0) {
            $qb->offset($this->offset);
        }

        HookRegistry::call('SubmissionFile::Collector::getQueryBuilder', [&$qb, $this]);

        return $qb;
    }
}
