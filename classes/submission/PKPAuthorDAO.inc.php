<?php

/**
 * @file classes/submission/PKPAuthorDAO.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorDAO
 * @ingroup submission
 * @see PKPAuthor
 *
 * @brief Operations for retrieving and modifying PKPAuthor objects.
 */

import('lib.pkp.classes.db.SchemaDAO');
import('lib.pkp.classes.submission.PKPAuthor');

abstract class PKPAuthorDAO extends SchemaDAO {
	/** @copydoc SchemaDao::$schemaName */
	public $schemaName = SCHEMA_AUTHOR;

	/** @copydoc SchemaDao::$tableName */
	public $tableName = 'authors';

	/** @copydoc SchemaDao::$settingsTableName */
	public $settingsTableName = 'author_settings';

	/** @copydoc SchemaDao::$primaryKeyColumn */
	public $primaryKeyColumn = 'author_id';

	/** @copydoc SchemaDao::$primaryTableColumns */
	public $primaryTableColumns = [
		'id' => 'author_id',
		'email' => 'email',
		'includeInBrowse' => 'include_in_browse',
		'publicationId' => 'publication_id',
		'seq' => 'seq',
		'userGroupId' => 'user_group_id',
	];

	/**
	 * Get a new data object
	 * @return DataObject
	 */
	function newDataObject() {
		return new Author();
	}

	/**
	 * @copydoc SchemaDAO::getById()
	 * Overrides the parent implementation to add the submission_locale column
	 */
	public function getById($objectId) {
		$result = $this->retrieve(
			'SELECT a.*, p.locale AS submission_locale FROM authors a JOIN publications p ON (a.publication_id = p.publication_id) WHERE author_id = ?',
			(int) $objectId
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve all authors for a publication.
	 * @param $publicationId int Publication ID.
	 * @param $sortByAuthorId bool Use author Ids as indexes in the array
	 * @param $useIncludeInBrowse bool Whether to limit to just include_in_browse authors
	 * @return array Authors ordered by sequence
	 */
	function getByPublicationId($publicationId, $sortByAuthorId = false, $useIncludeInBrowse = false) {
		$authors = array();
		$params = array((int) $publicationId);
		if ($useIncludeInBrowse) $params[] = 1;

		$result = $this->retrieve(
			'SELECT DISTINCT a.*, ug.show_title, p.locale AS submission_locale
			FROM authors a
				JOIN user_groups ug ON (a.user_group_id=ug.user_group_id)
				JOIN publications p ON (p.publication_id = a.publication_id)
				LEFT JOIN author_settings au ON (au.author_id = a.author_id)
			WHERE	a.publication_id = ? ' .
			($useIncludeInBrowse ? ' AND a.include_in_browse = ?' : '')
			. ' ORDER BY seq',
			$params
		);

		while (!$result->EOF) {
			$row = $result->getRowAssoc(false);
			if ($sortByAuthorId) {
				$authorId = $row['author_id'];
				$authors[$authorId] = $this->_fromRow($row);
			} else {
				$authors[] = $this->_fromRow($row);
			}
			$result->MoveNext();
		}

		$result->Close();
		return $authors;
	}

	/**
	 * Update author names when publication locale changes.
	 * @param $publicationId int
	 * @param $oldLocale string
	 * @param $newLocale string
	 */
	function changePublicationLocale($publicationId, $oldLocale, $newLocale) {
		$authors = $this->getByPublicationId($publicationId);
		foreach ($authors as $author) {
			if (empty($author->getGivenName($newLocale))) {
				if (empty($author->getFamilyName($newLocale)) && empty($author->getPreferredPublicName($newLocale))) {
					// if no name exists for the new locale
					// copy all names with the old locale to the new locale
					$author->setGivenName($author->getGivenName($oldLocale), $newLocale);
					$author->setFamilyName($author->getFamilyName($oldLocale), $newLocale);
					$author->setPreferredPublicName($author->getPreferredPublicName($oldLocale), $newLocale);
				} else {
					// if the given name does not exist, but one of the other names do exist
					// copy only the given name with the old locale to the new locale, because the given name is required
					$author->setGivenName($author->getGivenName($oldLocale), $newLocale);
				}
				$this->updateObject($author);
			}
		}
	}


	/**
	 * @copydoc SchemaDAO::_fromRow()
	 */
	public function _fromRow($primaryRow) {
		$author = parent::_fromRow($primaryRow);
		$author->setSubmissionLocale($primaryRow['submission_locale']);
		return $author;
	}

	/**
	 * Get the ID of the last inserted author.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('authors', 'author_id');
	}

	/**
	 * Delete authors by submission.
	 * @param $submissionId int
	 */
	function deleteBySubmissionId($submissionId) {
		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$submission = $submissionDao->getById($submissionId);
		if ($submission) foreach ($submission->getData('publications') as $publication) {
			$authors = $this->getByPublicationId($publication->getId());
			foreach ($authors as $author) {
				$this->deleteObject($author);
			}
		}
	}
}
