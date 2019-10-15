<?php

/**
 * @file classes/publication/PKPPublicationDAO.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicationDAO
 * @ingroup core
 * @see DAO
 *
 * @brief Operations for retrieving and modifying publication objects.
 */
import('lib.pkp.classes.db.SchemaDAO');
import('lib.pkp.classes.plugins.PKPPubIdPluginDAO');
import('classes.publication.Publication');

class PKPPublicationDAO extends SchemaDAO implements PKPPubIdPluginDAO {
	/** @copydoc SchemaDao::$schemaName */
	public $schemaName = SCHEMA_PUBLICATION;

	/** @copydoc SchemaDao::$tableName */
	public $tableName = 'publications';

	/** @copydoc SchemaDao::$settingsTableName */
	public $settingsTableName = 'publication_settings';

	/** @copydoc SchemaDao::$primaryKeyColumn */
	public $primaryKeyColumn = 'publication_id';

	/** @copydoc SchemaDao::$primaryTableColumns */
	public $primaryTableColumns = [
		'id' => 'publication_id',
		'accessStatus' => 'access_status',
		'datePublished' => 'date_published',
		'lastModified' => 'last_modified',
		'locale' => 'locale',
		'primaryContactId' => 'primary_contact_id',
		'sectionId' => 'section_id',
		'submissionId' => 'submission_id',
		'status' => 'status',
	];

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

		// Get authors
		$publication->setData('authors', iterator_to_array(
			Services::get('author')->getMany(['publicationIds' => $publication->getId()])
		));

		// Get controlled vocab metadata
		$submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
		$publication->setData('keywords', $submissionKeywordDao->getKeywords($publication->getId()));
		$submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO');
		$publication->setData('subjects', $submissionSubjectDao->getSubjects($publication->getId()));
		$submissionDisciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO');
		$publication->setData('disciplines', $submissionDisciplineDao->getDisciplines($publication->getId()));
		$submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO');
		$publication->setData('languages', $submissionLanguageDao->getLanguages($publication->getId()));
		$submissionAgencyDao = DAORegistry::getDAO('SubmissionAgencyDAO');
		$publication->setData('supportingAgencies', $submissionAgencyDao->getAgencies($publication->getId()));

		// Get categories
		$publication->setData('categoryIds', array_map(
			function($category) {
				return (int) $category->getId();
			},
			DAORegistry::getDAO('CategoryDAO')->getByPublicationId($publication->getId())->toArray()
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
						DAORegistry::getDAO('SubmissionKeywordDAO')->insertKeywords($value, $publication->getId());
						break;
					case 'subjects':
						DAORegistry::getDAO('SubmissionSubjectDAO')->insertSubjects($value, $publication->getId());
						break;
					case 'disciplines':
						DAORegistry::getDAO('SubmissionDisciplineDAO')->insertDisciplines($value, $publication->getId());
						break;
					case 'languages':
						DAORegistry::getDAO('SubmissionLanguageDAO')->insertLanguages($value, $publication->getId());
						break;
					case 'supportingAgencies':
						DAORegistry::getDAO('SubmissionAgencyDAO')->insertAgencies($value, $publication->getId());
						break;
				}
			}
		}

		// Set categories
		if (!empty($publication->getData('categoryIds'))) {
			foreach ($publication->getData('categoryIds') as $categoryId) {
				DAORegistry::getDAO('CategoryDAO')->insertPublicationAssignment($categoryId, $publication->getId());
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
						DAORegistry::getDAO('SubmissionKeywordDAO')->insertKeywords($value, $publication->getId());
						break;
					case 'subjects':
						DAORegistry::getDAO('SubmissionSubjectDAO')->insertSubjects($value, $publication->getId());
						break;
					case 'disciplines':
						DAORegistry::getDAO('SubmissionDisciplineDAO')->insertDisciplines($value, $publication->getId());
						break;
					case 'languages':
						DAORegistry::getDAO('SubmissionLanguageDAO')->insertLanguages($value, $publication->getId());
						break;
					case 'supportingAgencies':
						DAORegistry::getDAO('SubmissionAgencyDAO')->insertAgencies($value, $publication->getId());
						break;
				}
			}
		}

		// Set categories
		DAORegistry::getDAO('CategoryDAO')->deletePublicationAssignments($publication->getId());
		if (!empty($publication->getData('categoryIds'))) {
			foreach ($publication->getData('categoryIds') as $categoryId) {
				DAORegistry::getDAO('CategoryDAO')->insertPublicationAssignment($categoryId, $publication->getId());
			}
		}
	}

	/**
	 * @copydoc SchemaDAO::deleteById()
	 */
	public function deleteById($publicationId) {
		parent::deleteById($publicationId);

		// Delete authors
		$contributors = Services::get('author')->getMany(['publicationIds' => $publicationId]);
		foreach ($contributors as $contributor) {
			Services::get('author')->delete($contributor);
		}

		// Delete the controlled vocabulary
		// Insert an empty array will clear existing entries
		DAORegistry::getDAO('SubmissionKeywordDAO')->insertKeywords([], $publicationId);
		DAORegistry::getDAO('SubmissionSubjectDAO')->insertSubjects([], $publicationId);
		DAORegistry::getDAO('SubmissionDisciplineDAO')->insertDisciplines([], $publicationId);
		DAORegistry::getDAO('SubmissionLanguageDAO')->insertLanguages([], $publicationId);
		DAORegistry::getDAO('SubmissionAgencyDAO')->insertAgencies([], $publicationId);

		// Delete categories
		DAORegistry::getDAO('CategoryDAO')->deletePublicationAssignments($publicationId);

		// Delete citations
		DAORegistry::getDAO('CitationDAO')->deleteByPublicationId($publicationId);
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::pubIdExists()
	 */
	public function pubIdExists($pubIdType, $pubId, $excludePubObjectId, $contextId) {
		$result = $this->retrieve(
			'SELECT COUNT(*)
			FROM publication_settings ps
			LEFT JOIN publications p ON p.publication_id = ps.publication_id
			LEFT JOIN submissions s ON p.submission_id = s.submission_id
			WHERE ps.setting_name = ? and ps.setting_value = ? and s.submission_id <> ? AND s.context_id = ?',
			array(
				'pub-id::'.$pubIdType,
				$pubId,
				// The excludePubObjectId refers to the submission id
				// because multiple versions of the same submission
				// are allowed to share a DOI.
				(int) $excludePubObjectId,
				(int) $contextId
			)
		);
		$returner = $result->fields[0] ? true : false;
		$result->Close();
		return $returner;
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::changePubId()
	 */
	public function changePubId($pubObjectId, $pubIdType, $pubId) {
		$this->update(
			'UPDATE publication_settings ps
				SET ps.setting_value = ?
				WHERE ps.publication_id = ?
				AND ps.setting_name= ?',
			[
				$pubId,
				$pubObjectId,
				'pubid::' . $pubIdType,
			]
		);
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
				'pubid::' . $pubIdType,
			]
		);
		$this->flushCache();
	}

	/**
	 * @copydoc PKPPubIdPluginDAO::deleteAllPubIds()
	 */
	public function deleteAllPubIds($contextId, $pubIdType) {
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
		$this->flushCache();
	}
}
