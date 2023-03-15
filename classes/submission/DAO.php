<?php
/**
 * @file classes/submission/DAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submission
 *
 * @brief Read and write submissions to the database.
 */

namespace PKP\submission;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Collector;
use APP\submission\Submission;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\core\traits\EntityWithParent;
use PKP\db\DAORegistry;
use PKP\services\PKPSchemaService;

class DAO extends EntityDAO
{
    use EntityWithParent;

    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_SUBMISSION;

    /** @copydoc EntityDAO::$table */
    public $table = 'submissions';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'submission_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'submission_id';

    /** @copydoc SchemaDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'submission_id',
        'contextId' => 'context_id',
        'currentPublicationId' => 'current_publication_id',
        'dateLastActivity' => 'date_last_activity',
        'dateSubmitted' => 'date_submitted',
        'lastModified' => 'last_modified',
        'locale' => 'locale',
        'stageId' => 'stage_id',
        'status' => 'status',
        'submissionProgress' => 'submission_progress',
    ];

    /**
     * Get the parent object ID column name
     */
    public function getParentColumn(): string
    {
        return 'context_id';
    }

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Submission
    {
        return app(Submission::class);
    }

    /**
     * Get the total count of submissions matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->count();
    }

    /**
     * Get a list of ids matching the configured query
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('s.' . $this->primaryKeyColumn)
            ->pluck('s.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of announcements matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->submission_id => $this->fromRow($row);
            }
        });
    }

    /**
     * Get the submission id by its url path
     */
    public function getIdByUrlPath(string $urlPath, int $contextId): ?int
    {
        $publication = DB::table('publications as p')
            ->leftJoin('submissions as s', 's.submission_id', '=', 'p.submission_id')
            ->where('s.context_id', '=', $contextId)
            ->where('p.url_path', '=', $urlPath)
            ->first();

        return $publication
            ? $publication->submission_id
            : null;
    }

    /**
     * Get submission ids that have a matching setting
     */
    public function getIdsBySetting(string $settingName, $settingValue, int $contextId): Enumerable
    {
        return DB::table($this->table . ' as s')
            ->join($this->settingsTable . ' as ss', 's.submission_id', '=', 'ss.submission_id')
            ->where('ss.setting_name', '=', $settingName)
            ->where('ss.setting_value', '=', $settingValue)
            ->where('s.context_id', '=', (int) $contextId)
            ->select('s.submission_id')
            ->pluck('s.submission_id');
    }

    /**
     * Retrieve a submission by public id
     *
     * @param string $pubIdType One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
     * @param null|mixed $contextId
     */
    public function getByPubId(string $pubIdType, string $pubId, $contextId = null): ?Submission
    {
        // Add check for incoming DOI request for legacy calls that bypass the Submission Repository
        if ($pubIdType == 'doi') {
            return $this->getByDoi($pubId, $contextId);
        } else {
            $qb = DB::table('publication_settings ps')
                ->join('publications p', 'p.publication_id', '=', 'ps.publication_id')
                ->join('submissions s', 'p.publication_id', '=', 's.current_publication_id')
                ->where('ps.setting_name', '=', 'pub-id::' . $pubIdType)
                ->where('ps.setting_value', '=', $pubId);

            if ($contextId) {
                $qb->where('s.context_id', '=', (int) $contextId);
            }

            $row = $qb->get(['s.submission_id']);

            return $row
                ? $this->get($row->submission_id)
                : null;
        }
    }

    /**
     * Retrieve a submission by its current publication's DOI
     */
    public function getByDoi(string $doi, int $contextId): ?Submission
    {
        $q = DB::table($this->table, 's')
            ->leftJoin('publications AS p', 'p.publication_id', '=', 's.current_publication_id')
            ->leftJoin('dois AS d', 'd.doi_id', '=', 'p.doi_id')
            ->where('d.doi', '=', $doi)
            ->where('s.context_id', '=', $contextId);
        $row = $q->select(['s.submission_id AS submission_id'])->get()->first();
        return $row ? $this->get($row->submission_id) : null;
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): Submission
    {
        $submission = parent::fromRow($row);

        $submission->setData(
            'publications',
            Repo::publication()->getCollector()
                ->filterBySubmissionIds([$submission->getId()])
                ->getMany()
                ->remember()
        );

        return $submission;
    }

    /**
     * @copydoc EntityDAO::_insert()
     */
    public function insert(Submission $submission): int
    {
        return parent::_insert($submission);
    }

    /**
     * @copydoc EntityDAO::_update()
     */
    public function update(Submission $submission)
    {
        parent::_update($submission);
    }

    /**
     * @copydoc EntityDAO::_delete()
     */
    public function delete(Submission $submission)
    {
        parent::_delete($submission);
    }

    /**
     * @copydoc \PKP\core\EntityDAO::deleteById()
     */
    public function deleteById(int $id)
    {
        $submission = Repo::submission()->get($id);

        // Delete publications
        $publications = Repo::publication()->getCollector()
            ->filterBySubmissionIds([$id])
            ->getMany();

        foreach ($publications as $publication) {
            Repo::publication()->delete($publication);
        }

        // Delete submission files.
        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->getMany();

        foreach ($submissionFiles as $submissionFile) {
            Repo::submissionFile()->delete($submissionFile);
        }

        Repo::decision()->deleteBySubmissionId($id);

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignmentDao->deleteBySubmissionId($id);

        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao->deleteBySubmissionId($id);

        // Delete the queries associated with a submission
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $queryDao->deleteByAssoc(Application::ASSOC_TYPE_SUBMISSION, $id);

        // Delete the stage assignments.
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId($id);
        while ($stageAssignment = $stageAssignments->next()) {
            $stageAssignmentDao->deleteObject($stageAssignment);
        }

        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        $noteDao->deleteByAssoc(Application::ASSOC_TYPE_SUBMISSION, $id);

        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /** @var SubmissionCommentDAO $submissionCommentDao */
        $submissionCommentDao->deleteBySubmissionId($id);

        // Delete any outstanding notifications for this submission
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $notificationDao->deleteByAssoc(Application::ASSOC_TYPE_SUBMISSION, $id);

        $submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO'); /** @var SubmissionEventLogDAO $submissionEventLogDao */
        $submissionEventLogDao->deleteByAssoc(Application::ASSOC_TYPE_SUBMISSION, $id);

        $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /** @var SubmissionEmailLogDAO $submissionEmailLogDao */
        $submissionEmailLogDao->deleteByAssoc(Application::ASSOC_TYPE_SUBMISSION, $id);

        parent::deleteById($id);
    }
}
