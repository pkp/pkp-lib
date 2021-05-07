<?php

/**
 * @file classes/submission/PKPSubmissionDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionDAO
 * @ingroup submission
 *
 * @see Submission
 *
 * @brief Operations for retrieving and modifying Submission objects.
 */

namespace PKP\submission;

use APP\core\Services;
use APP\submission\Submission;

use Exception;
use Illuminate\Support\Facades\DB;
use PKP\cache\CacheManager;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;

use PKP\db\SchemaDAO;
use PKP\services\PKPSchemaService;

abstract class PKPSubmissionDAO extends SchemaDAO
{
    public const ORDERBY_DATE_PUBLISHED = 'datePublished';
    public const ORDERBY_TITLE = 'title';

    public $cache;
    public $authorDao;

    /** @copydoc SchemaDAO::$schemaName */
    public $schemaName = PKPSchemaService::SCHEMA_SUBMISSION;

    /** @copydoc SchemaDAO::$tableName */
    public $tableName = 'submissions';

    /** @copydoc SchemaDAO::$settingsTableName */
    public $settingsTableName = 'submission_settings';

    /** @copydoc SchemaDAO::$primaryKeyColumn */
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
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->authorDao = DAORegistry::getDAO('AuthorDAO');
    }

    /**
     * Callback for a cache miss.
     *
     * @param $cache Cache
     * @param $id string
     *
     * @return Submission
     */
    public function _cacheMiss($cache, $id)
    {
        $submission = $this->getById($id, null, false);
        $cache->setCache($id, $submission);
        return $submission;
    }

    /**
     * Get the submission cache.
     *
     * @return Cache
     */
    public function _getCache()
    {
        if (!isset($this->cache)) {
            $cacheManager = CacheManager::getManager();
            $this->cache = $cacheManager->getObjectCache('submissions', 0, [&$this, '_cacheMiss']);
        }
        return $this->cache;
    }

    /**
     * @copydoc SchemaDAO::_fromRow()
     */
    public function _fromRow($row)
    {
        $submission = parent::_fromRow($row);
        $submission->setData('publications', iterator_to_array(
            Services::get('publication')->getMany(['submissionIds' => $submission->getId()])
        ));

        return $submission;
    }

    /**
     * Delete a submission.
     *
     * @param $submission Submission
     */
    public function deleteObject($submission)
    {
        return $this->deleteById($submission->getId());
    }

    /**
     * Delete a submission by ID.
     *
     * @param $submissionId int
     */
    public function deleteById($submissionId)
    {
        $submission = $this->getById($submissionId);
        if (!$submission instanceof Submission) {
            throw new Exception('Could not delete submission. No submission with the id ' . (int) $submissionId . ' was found.');
        }

        // Delete publications
        $publicationsIterator = Services::get('publication')->getMany(['submissionIds' => $submissionId]);
        $publicationDao = DAORegistry::getDAO('PublicationDAO'); /** @var PublicationDAO $publicationDao */
        foreach ($publicationsIterator as $publication) {
            $publicationDao->deleteObject($publication);
        }

        // Delete submission files.
        $submissionFilesIterator = Services::get('submissionFile')->getMany([
            'submissionIds' => [$submission->getId()],
        ]);
        foreach ($submissionFilesIterator as $submissionFile) {
            Services::get('submissionFile')->delete($submissionFile);
        }

        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao->deleteBySubmissionId($submissionId);

        $editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /** @var EditDecisionDAO $editDecisionDao */
        $editDecisionDao->deleteDecisionsBySubmissionId($submissionId);

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignmentDao->deleteBySubmissionId($submissionId);

        // Delete the queries associated with a submission
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $queryDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

        // Delete the stage assignments.
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submissionId);
        while ($stageAssignment = $stageAssignments->next()) {
            $stageAssignmentDao->deleteObject($stageAssignment);
        }

        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        $noteDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /** @var SubmissionCommentDAO $submissionCommentDao */
        $submissionCommentDao->deleteBySubmissionId($submissionId);

        // Delete any outstanding notifications for this submission
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $notificationDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

        $submissionEventLogDao = DAORegistry::getDAO('SubmissionEventLogDAO'); /** @var SubmissionEventLogDAO $submissionEventLogDao */
        $submissionEventLogDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

        $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /** @var SubmissionEmailLogDAO $submissionEmailLogDao */
        $submissionEmailLogDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);

        parent::deleteById($submissionId);
    }

    /**
     * Retrieve submission by public id
     *
     * @param $pubIdType string One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
     * @param $pubId string
     * @param $contextId int
     *
     * @return Submission|null
     */
    public function getByPubId($pubIdType, $pubId, $contextId = null)
    {
        $params = [
            'pub-id::' . $pubIdType,
            $pubId,
        ];
        if ($contextId) {
            $params[] = (int) $contextId;
        }

        $result = $this->retrieve(
            'SELECT s.submission_id
				FROM publication_settings ps
				INNER JOIN publications p ON p.publication_id = ps.publication_id
				INNER JOIN submissions s ON p.publication_id = s.current_publication_id
				WHERE ps.setting_name = ? AND ps.setting_value = ?'
                . ($contextId ? ' AND s.context_id = ?' : ''),
            $params
        );
        $row = $result->current();
        return $row ? $this->getById($row->submission_id) : null;
    }


    /**
     * Get the ID of the last inserted submission.
     *
     * @return int
     */
    public function getInsertId()
    {
        return $this->_getInsertId('submissions', 'submission_id');
    }

    /**
     * Flush the submission cache.
     */
    public function flushCache()
    {
        $cache = $this->_getCache();
        $cache->flush();
    }

    /**
     * Get all submissions for a context.
     *
     * @param $contextId int
     *
     * @return DAOResultFactory containing matching Submissions
     */
    public function getByContextId($contextId)
    {
        $result = $this->retrieve(
            'SELECT * FROM ' . $this->tableName . ' WHERE context_id = ?',
            [(int) $contextId]
        );

        return new DAOResultFactory($result, $this, '_fromRow');
    }

    /*
     * Delete all submissions by context ID.
     * @param $contextId int
     */
    public function deleteByContextId($contextId)
    {
        $submissions = $this->getByContextId($contextId);
        while ($submission = $submissions->next()) {
            $this->deleteById($submission->getId());
        }
    }

    /**
     * Reset the attached licenses of all submissions in a context to context defaults.
     *
     * @param $contextId int
     */
    public function resetPermissions($contextId)
    {
        $submissions = $this->getByContextId($contextId);
        while ($submission = $submissions->next()) {
            $publications = (array) $submission->getData('publications');
            if (empty($publications)) {
                continue;
            }
            $params = [
                'copyrightYear' => $submission->_getContextLicenseFieldValue(null, PERMISSIONS_FIELD_COPYRIGHT_YEAR),
                'copyrightHolder' => $submission->_getContextLicenseFieldValue(null, PERMISSIONS_FIELD_COPYRIGHT_HOLDER),
                'licenseUrl' => $submission->_getContextLicenseFieldValue(null, PERMISSIONS_FIELD_LICENSE_URL),
            ];
            foreach ($publications as $publication) {
                $publication = Services::get('publication')->edit($publication, $params, Application::get()->getRequest());
            }
        }
        $this->flushCache();
    }

    /**
     * Get default sort option.
     *
     * @return string
     */
    public function getDefaultSortOption()
    {
        return $this->getSortOption(self::ORDERBY_DATE_PUBLISHED, SORT_DIRECTION_DESC);
    }

    /**
     * Get possible sort options.
     *
     * @return array
     */
    public function getSortSelectOptions()
    {
        return [
            $this->getSortOption(self::ORDERBY_TITLE, SORT_DIRECTION_ASC) => __('catalog.sortBy.titleAsc'),
            $this->getSortOption(self::ORDERBY_TITLE, SORT_DIRECTION_DESC) => __('catalog.sortBy.titleDesc'),
            $this->getSortOption(self::ORDERBY_DATE_PUBLISHED, SORT_DIRECTION_ASC) => __('catalog.sortBy.datePublishedAsc'),
            $this->getSortOption(self::ORDERBY_DATE_PUBLISHED, SORT_DIRECTION_DESC) => __('catalog.sortBy.datePublishedDesc'),
        ];
    }

    /**
     * Get sort option.
     *
     * @param $sortBy string
     * @param $sortDir int
     *
     * @return string
     */
    public function getSortOption($sortBy, $sortDir)
    {
        return $sortBy . '-' . $sortDir;
    }

    /**
     * Get sort way for a sort option.
     *
     * @param $sortOption string concat(sortBy, '-', sortDir)
     *
     * @return string
     */
    public function getSortBy($sortOption)
    {
        [$sortBy, $sortDir] = explode('-', $sortOption);
        return $sortBy;
    }

    /**
     * Get sort direction for a sort option.
     *
     * @param $sortOption string concat(sortBy, '-', sortDir)
     *
     * @return int
     */
    public function getSortDirection($sortOption)
    {
        [$sortBy, $sortDir] = explode('-', $sortOption);
        return $sortDir;
    }

    /**
     * Find submission ids by querying settings.
     *
     * @param $settingName string
     * @param $settingValue mixed
     * @param $contextId int
     *
     * @return array Submission.
     */
    public function getIdsBySetting($settingName, $settingValue, $contextId)
    {
        $q = DB::table('submissions as s')
            ->join('submission_settings as ss', 's.submission_id', '=', 'ss.submission_id')
            ->where('ss.setting_name', '=', $settingName)
            ->where('ss.setting_value', '=', $settingValue)
            ->where('s.context_id', '=', (int) $contextId);

        return $q->select('s.submission_id')
            ->pluck('s.submission_id')
            ->toArray();
    }

    /**
     * Check if the submission ID exists.
     *
     * @param $submissionId int
     * @param $contextId int, optional
     *
     * @return boolean
     */
    public function exists($submissionId, $contextId = null)
    {
        $q = DB::table('submissions');
        $q->where('submission_id', '=', $submissionId);
        if ($contextId) {
            $q->where('context_id', '=', $contextId);
        }
        return $q->exists();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\PKPSubmissionDAO', '\PKPSubmissionDAO');
    foreach (['ORDERBY_DATE_PUBLISHED', 'ORDERBY_TITLE'] as $constantName) {
        define($constantName, constant('\PKPSubmissionDAO::' . $constantName));
    }
}
