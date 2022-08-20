<?php
/**
 * @file classes/submissionFile/Collector.php
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

class Collector implements CollectorInterface
{
    /** @var DAO */
    public $dao;

    /** @var null|array get submission files for one or more file stages */
    protected $fileStages = null;

    /** @var null|array get submission files for one or more genres */
    protected $genreIds = null;

    /** @var null|array get submission files for one or more review rounds */
    protected $reviewRoundIds = null;

    /** @var null|array get submission files for one or more review assignments */
    protected $reviewIds = null;

    /** @var null|array get submission files for one or more submissions */
    protected $submissionIds = null;

    /** @var null|array get submission files matching one or more files */
    protected $fileIds = null;

    /** @var null|string get submission files matching one ASSOC_TYPE */
    protected $assocType = null;

    /** @var null|array get submission files matching an ASSOC_ID with one of the assocTypes */
    protected $assocIds = null;

    /** @var bool include submission files in the SUBMISSION_FILE_DEPENDENT stage */
    protected $includeDependentFiles = false;

    /** @var null|array get submission files matching one or more uploader users id */
    protected $uploaderUserIds = null;

    /** @var null|int */
    public $count = null;

    /** @var null|int */
    public $offset = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    public function getIds(): Collection
    {
        return $this->dao->getIds($this);
    }

    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    /**
     * Set fileStages filter
     */
    public function filterByFileStages(?array $fileStages): self
    {
        $this->fileStages = $fileStages;

        return $this;
    }

    /**
     * Set genreIds filter
     */
    public function filterByGenreIds(?array $genreIds): self
    {
        $this->genreIds = $genreIds;

        return $this;
    }

    /**
     * Set review rounds filter
     */
    public function filterByReviewRoundIds(?array $reviewRoundIds): self
    {
        $this->reviewRoundIds = $reviewRoundIds;

        return $this;
    }

    /**
     * Set review assignments filter
     */
    public function filterByReviewIds(?array $reviewIds): self
    {
        $this->reviewIds = $reviewIds;

        return $this;
    }

    /**
     * Set submissionIds filter
     */
    public function filterBySubmissionIds(?array $submissionIds): self
    {
        $this->submissionIds = $submissionIds;

        return $this;
    }

    /**
     * Set fileIds filter
     */
    public function filterByFileIds(?array $fileIds): self
    {
        $this->fileIds = $fileIds;

        return $this;
    }

    /**
     * Set assocType and assocId filters
     *
     * @param null|int $assocType One of the ASSOC_TYPE_ constants
     * @param null|array $assocIds Match for the specified assoc type
     */
    public function filterByAssoc(?int $assocType, ?array $assocIds = null): self
    {
        $this->assocType = $assocType;
        $this->assocIds = $assocIds;

        return $this;
    }

    /**
     * Set uploaderUserIds filter
     */
    public function filterByUploaderUserIds(?array $uploaderUserIds): self
    {
        $this->uploaderUserIds = $uploaderUserIds;

        return $this;
    }

    /**
     * Whether or not to include dependent files in the results
     */
    public function includeDependentFiles(bool $includeDependentFiles = true): self
    {
        $this->includeDependentFiles = $includeDependentFiles;

        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(?int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(?int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     */
    public function getQueryBuilder(): Builder
    {
        $qb = DB::table($this->dao->table . ' as sf')
            ->join('submissions as s', 's.submission_id', '=', 'sf.submission_id')
            ->join('files as f', 'f.file_id', '=', 'sf.file_id')
            ->select(['sf.*', 'f.*', 's.locale as locale']);

        if ($this->submissionIds !== null) {
            $qb->whereIn('sf.submission_id', $this->submissionIds);
        }

        if ($this->fileStages !== null) {
            $qb->whereIn('sf.file_stage', $this->fileStages);
        }

        if ($this->genreIds !== null) {
            $qb->whereIn('sf.genre_id', $this->genreIds);
        }

        if ($this->fileIds !== null) {
            $qb->whereIn('sf.submission_file_id', function ($query) {
                return $query->select('submission_file_id')
                    ->from('submission_file_revisions')
                    ->whereIn('file_id', $this->fileIds);
            });
        }

        if ($this->reviewRoundIds !== null) {
            $qb->whereIn('sf.submission_file_id', function ($query) {
                return $query->select('submission_file_id')
                    ->from('review_round_files')
                    ->whereIn('review_round_id', $this->reviewRoundIds);
            });
        }

        if ($this->reviewIds !== null) {
            $qb->join('review_files as rf', 'rf.submission_file_id', '=', 'sf.submission_file_id')
                ->whereIn('rf.review_id', $this->reviewIds);
        }

        if ($this->assocType !== null) {
            $qb->where('sf.assoc_type', $this->assocType);

            if ($this->assocIds !== null) {
                $qb->whereIn('sf.assoc_id', $this->assocIds);
            }
        }

        if ($this->uploaderUserIds !== null) {
            $qb->whereIn('sf.uploader_user_id', $this->uploaderUserIds);
        }

        if ($this->includeDependentFiles !== true && $this->fileStages !== null && !in_array(SubmissionFile::SUBMISSION_FILE_DEPENDENT, $this->fileStages)) {
            $qb->where('sf.file_stage', '!=', SubmissionFile::SUBMISSION_FILE_DEPENDENT);
        }

        $qb->orderBy('sf.created_at', 'desc');

        if ($this->count !== null) {
            $qb->limit($this->count);
        }

        if ($this->offset !== null) {
            $qb->offset($this->offset);
        }

        Hook::call('SubmissionFile::Collector::getQueryBuilder', [&$qb, $this]);

        return $qb;
    }
}
