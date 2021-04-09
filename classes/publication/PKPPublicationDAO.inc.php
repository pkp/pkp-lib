<?php

/**
 * @file classes/publication/PKPPublicationDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicationDAO
 * @ingroup core
 * @see DAO
 *
 * @brief Operations for retrieving and modifying publication objects.
 */
use Illuminate\Database\Capsule\Manager as Capsule;

import('lib.pkp.classes.db.SchemaDAO');
import('lib.pkp.classes.plugins.PKPPubIdPluginDAO');
import('classes.publication.Publication');
import('lib.pkp.classes.services.PKPSchemaService'); // SCHEMA_ constants

class PKPPublicationDAO extends SchemaDAO implements PKPPubIdPluginDAO {
	/** @copydoc SchemaDAO::$schemaName */
	public $schemaName = SCHEMA_PUBLICATION;

	/** @copydoc SchemaDAO::$tableName */
	public $tableName = 'publications';

	/** @copydoc SchemaDAO::$settingsTableName */
	public $settingsTableName = 'publication_settings';

	/** @copydoc SchemaDAO::$primaryKeyColumn */
	public $primaryKeyColumn = 'publication_id';

	/** @var array List of properties that are stored in the controlled_vocab tables. */
	public $controlledVocabProps = ['disciplines', 'keywords', 'languages', 'subjects', 'supportingAgencies'];

	/**
	 * Create a new DataObject of the appropriate class
	 *
	 * @return DataObject
	 */
	public function newDataObject() {
		return new Publication();
	}

	/**
	 * @copydoc SchemaDAO::_fromRow()
	 */
	public function _fromRow($primaryRow) {
		$publication = parent::_fromRow($primaryRow);

		// Set the primary locale from the submission
		$locale = Capsule::table('submissions as s')
			->where('s.submission_id', '=', $publication->getData('submissionId'))
			->value('locale');
		$publication->setData('locale', $locale);

		// Get authors
		$publication->setData('authors', iterator_to_array(
			Services::get('author')->getMany(['publicationIds' => $publication->getId()])
		));

		// Get controlled vocab metadata
		$submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO'); /* @var $submissionKeywordDao SubmissionKeywordDAO */
		$publication->setData('keywords', $submissionKeywordDao->getKeywords($publication->getId()));
		$submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO'); /* @var $submissionSubjectDao SubmissionSubjectDAO */
		$publication->setData('subjects', $submissionSubjectDao->getSubjects($publication->getId()));
		$submissionDisciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO'); /* @var $submissionDisciplineDao SubmissionDisciplineDAO */
		$publication->setData('disciplines', $submissionDisciplineDao->getDisciplines($publication->getId()));
		$submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO'); /* @var $submissionLanguageDao SubmissionLanguageDAO */
		$publication->setData('languages', $submissionLanguageDao->getLanguages($publication->getId()));
		$submissionAgencyDao = DAORegistry::getDAO('SubmissionAgencyDAO'); /* @var $submissionAgencyDao SubmissionAgencyDAO */
		$publication->setData('supportingAgencies', $submissionAgencyDao->getAgencies($publication->getId()));

		// Get categories
		$categoryDao = DAORegistry::getDAO('CategoryDAO'); /* @var $categoryDao CategoryDAO */
		$publication->setData('categoryIds', array_map(
			function($category) {
				return (int) $category->getId();
			},
			$categoryDao->getByPublicationId($publication->getId())->toArray()
		));

		return $publication;
	}

	/**
	 * @copydoc SchemaDAO::insertObject()
	 */
	public function insertObject($publication) {

		// Remove the controlled vocabulary from the publication to save it separately
		$controlledVocabKeyedArray = array_flip($this->controlledVocabProps);
		$controlledVocabProps = array_intersect_key($publication->_data, $controlledVocabKeyedArray);
		$publication->_data = array_diff_key($publication->_data, $controlledVocabKeyedArray);

		parent::insertObject($publication);

		// Add controlled vocabularly for which we have props
		if (!empty($controlledVocabProps)) {
			foreach ($controlledVocabProps as $prop => $value) {
				switch ($prop) {
					case 'keywords':
						$submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO'); /* @var $submissionKeywordDao SubmissionKeywordDAO */
						$submissionKeywordDao->insertKeywords($value, $publication->getId());
						break;
					case 'subjects':
						$submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO'); /* @var $submissionSubjectDao SubmissionSubjectDAO */
						$submissionSubjectDao->insertSubjects($value, $publication->getId());
						break;
					case 'disciplines':
						$submissionDisciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO'); /* @var $submissionDisciplineDao SubmissionDisciplineDAO */
						$submissionDisciplineDao->insertDisciplines($value, $publication->getId());
						break;
					case 'languages':
						$submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO'); /* @var $submissionLanguageDao SubmissionLanguageDAO */
						$submissionLanguageDao->insertLanguages($value, $publication->getId());
						break;
					case 'supportingAgencies':
						$submissionAgencyDao = DAORegistry::getDAO('SubmissionAgencyDAO'); /* @var $submissionAgencyDao SubmissionAgencyDAO */
						$submissionAgencyDao->insertAgencies($value, $publication->getId());
						break;
				}
			}
		}

		// Set categories
		if (!empty($publication->getData('categoryIds'))) {
			$categoryDao = DAORegistry::getDAO('CategoryDAO'); /* @var $categoryDao CategoryDAO */
			foreach ($publication->getData('categoryIds') as $categoryId) {
				$categoryDao->insertPublicationAssignment($categoryId, $publication->getId());
			}
		}

		return $publication->getId();
	}

	/**
	 * @copydoc SchemaDAO::updateObject()
	 */
	public function updateObject($publication)	{

		// Remove the controlled vocabulary from the publication to save it separately
		$controlledVocabKeyedArray = array_flip($this->controlledVocabProps);
		$controlledVocabProps = array_intersect_key($publication->_data, $controlledVocabKeyedArray);
		$publication->_data = array_diff_key($publication->_data, $controlledVocabKeyedArray);

		parent::updateObject($publication);

		// Update controlled vocabularly for which we have props
		if (!empty($controlledVocabProps)) {
			foreach ($controlledVocabProps as $prop => $value) {
				switch ($prop) {
					case 'keywords':
						$submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO'); /* @var $submissionKeywordDao SubmissionKeywordDAO */
						$submissionKeywordDao->insertKeywords($value, $publication->getId());
						break;
					case 'subjects':
						$submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO'); /* @var $submissionSubjectDao SubmissionSubjectDAO */
						$submissionSubjectDao->insertSubjects($value, $publication->getId());
						break;
					case 'disciplines':
						$submissionDisciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO'); /* @var $submissionDisciplineDao SubmissionDisciplineDAO */
						$submissionDisciplineDao->insertDisciplines($value, $publication->getId());
						break;
					case 'languages':
						$submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO'); /* @var $submissionLanguageDao SubmissionLanguageDAO */
						$submissionLanguageDao->insertLanguages($value, $publication->getId());
						break;
					case 'supportingAgencies':
						$submissionAgencyDao = DAORegistry::getDAO('SubmissionAgencyDAO'); /* @var $submissionAgencyDao SubmissionAgencyDAO */
						$submissionAgencyDao->insertAgencies($value, $publication->getId());
						break;
				}
			}
		}

		// Set categories
		$categoryDao = DAORegistry::getDAO('CategoryDAO'); /* @var $categoryDao CategoryDAO */
		$categoryDao->deletePublicationAssignments($publication->getId());
		if (!empty($publication->getData('categoryIds'))) {
			foreach ($publication->getData('categoryIds') as $categoryId) {
				$categoryDao->insertPublicationAssignment($categoryId, $publication->getId());
			}
		}
	}

	/**
	 * @copydoc SchemaDAO::deleteById()
	 */
	public function deleteById($publicationId) {
		parent::deleteById($publicationId);

		// Delete authors
		$authorsIterator = Services::get('author')->getMany(['publicationIds' => $publicationId]);
		foreach ($authorsIterator as $author) {
			Services::get('author')->delete($author);
		}

		// Delete the controlled vocabulary
		// Insert an empty array will clear existing entries
		$submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO'); /* @var $submissionKeywordDao SubmissionKeywordDAO */
		$submissionKeywordDao->insertKeywords([], $publicationId);
		$submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO'); /* @var $submissionSubjectDao SubmissionSubjectDAO */
		$submissionSubjectDao->insertSubjects([], $publicationId);
		$submissionDisciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO'); /* @var $submissionDisciplineDao SubmissionDisciplineDAO */
		$submissionDisciplineDao->insertDisciplines([], $publicationId);
		$submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO'); /* @var $submissionLanguageDao SubmissionLanguageDAO */
		$submissionLanguageDao->insertLanguages([], $publicationId);
		$submissionAgencyDao = DAORegistry::getDAO('SubmissionAgencyDAO'); /* @var $submissionAgencyDao SubmissionAgencyDAO */
		$submissionAgencyDao->insertAgencies([], $publicationId);

		// Delete categories
		$categoryDao = DAORegistry::getDAO('CategoryDAO'); /* @var $categoryDao CategoryDAO */
		$categoryDao->deletePublicationAssignments($publicationId);

		// Delete citations
		$citationDao = DAORegistry::getDAO('CitationDAO'); /* @var $citationDao CitationDAO */
		$citationDao->deleteByPublicationId($publicationId);
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::pubIdExists()
	 */
	public function pubIdExists($pubIdType, $pubId, $excludePubObjectId, $contextId) {
		$result = $this->retrieve(
			'SELECT COUNT(*) AS row_count
			FROM publication_settings ps
			LEFT JOIN publications p ON p.publication_id = ps.publication_id
			LEFT JOIN submissions s ON p.submission_id = s.submission_id
			WHERE ps.setting_name = ? and ps.setting_value = ? and s.submission_id <> ? AND s.context_id = ?',
			[
				'pub-id::'.$pubIdType,
				$pubId,
				// The excludePubObjectId refers to the submission id
				// because multiple versions of the same submission
				// are allowed to share a DOI.
				(int) $excludePubObjectId,
				(int) $contextId
			]
		);
		$row = $result->current();
		return $row ? (boolean) $row->row_count : false;
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::changePubId()
	 */
	function changePubId($pubObjectId, $pubIdType, $pubId) {
		$idFields = ['publication_id', 'locale', 'setting_name'];
		$updateArray = [
			'publication_id' => (int) $pubObjectId,
			'locale' => '',
			'setting_name' => 'pub-id::' . $pubIdType,
			'setting_value' => (string) $pubId
		];
		$this->replace('publication_settings', $updateArray, $idFields);
		$this->flushCache();
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::deletePubId()
	 */
	public function deletePubId($pubObjectId, $pubIdType) {
		$this->update(
			'DELETE FROM publication_settings ps
				WHERE ps.publication_id = ?
				AND ps.setting_name= ?',
			[
				$pubObjectId,
				'pub-id::' . $pubIdType,
			]
		);
		$this->flushCache();
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::deleteAllPubIds()
	 */
	public function deleteAllPubIds($contextId, $pubIdType) {
		switch (Capsule::connection()->getDriverName()) {
			case 'mysql':
				$this->update(
					'DELETE ps FROM publication_settings ps
						LEFT JOIN publications p ON p.publication_id = ps.publication_id
						LEFT JOIN submissions s ON s.submission_id = p.submission_id
						WHERE ps.setting_name = ?
						AND s.context_id = ?',
					[
						'pub-id::' . $pubIdType,
						$contextId,
					]
				);
				break;
			case 'pgsql':
				$this->update(
					'DELETE FROM publication_settings
					USING publication_settings ps
						LEFT JOIN publications p ON p.publication_id = ps.publication_id
						LEFT JOIN submissions s ON s.submission_id = p.submission_id
					WHERE	ps.setting_name = ?
						AND s.context_id = ?
						AND ps.publication_id = publication_settings.publication_id
						AND ps.locale = publication_settings.locale
						AND ps.setting_name = publication_settings.setting_name',
					[
						'pub-id::' . $pubIdType,
						$contextId,
					]
				);
				break;
			default: fatalError("Unknown database type!");
		}
		$this->flushCache();
	}

	/**
	 * Find publication ids by querying settings.
	 * @param $settingName string
	 * @param $settingValue mixed
	 * @param $contextId int
	 * @return array Publication.
	 */
	public function getIdsBySetting($settingName, $settingValue, $contextId) {
		$q = Capsule::table('publications as p')
			->join('publication_settings as ps', 'p.publication_id', '=', 'ps.publication_id')
			->join('submissions as s', 'p.submission_id', '=', 's.submission_id')
			->where('ps.setting_name', '=', $settingName)
			->where('ps.setting_value', '=', $settingValue)
			->where('s.context_id', '=', (int) $contextId);

		return $q->select('p.publication_id')
			->pluck('p.publication_id')
			->toArray();
	}

	/**
	 * Check if the publication ID exists.
	 * @param $publicationId int
	 * @param $submissionId int, optional
	 * @return boolean
	 */
	function exists($publicationId, $submissionId = null) {
		$q = Capsule::table('publications');
		$q->where('publication_id', '=', $publicationId);
		if ($submissionId) {
			$q->where('submission_id', '=', $submissionId);
		}
		return $q->exists();
	}
}
