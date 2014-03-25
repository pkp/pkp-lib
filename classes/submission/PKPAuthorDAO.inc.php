<?php

/**
 * @file classes/submission/PKPAuthorDAO.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorDAO
 * @ingroup submission
 * @see PKPAuthor
 *
 * @brief Operations for retrieving and modifying PKPAuthor objects.
 */


import('lib.pkp.classes.submission.PKPAuthor');

class PKPAuthorDAO extends DAO {
	/**
	 * Constructor
	 */
	function PKPAuthorDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve an author by ID.
	 * @param $authorId int Author ID
	 * @param $submissionId int optional
	 * @return Author
	 */
	function getById($authorId, $submissionId = null) {
		$params = array((int) $authorId);
		if ($submissionId !== null) $params[] = (int) $submissionId;
		$result = $this->retrieve(
			'SELECT a.*,
				ug.show_title
			FROM	authors a
				JOIN user_groups ug ON (a.user_group_id=ug.user_group_id)
			WHERE	a.author_id = ?'
				. ($submissionId !== null?' AND a.submission_id = ?':''),
			$params
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve all authors for a submission.
	 * @param $submissionId int
	 * @param $sortByAuthorId bool Use author Ids as indexes in the array
	 * @return array Authors ordered by sequence
	 */
	function getBySubmissionId($submissionId, $sortByAuthorId = false) {
		$authors = array();

		$result = $this->retrieve(
			'SELECT	a.*, ug.show_title
			FROM	authors a
				JOIN user_groups ug ON (a.user_group_id=ug.user_group_id)
			WHERE	a.submission_id = ?
			ORDER BY seq',
			(int) $submissionId
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
	 * Retrieve the number of authors assigned to a submission
	 * @param $submissionId int
	 * @return int
	 */
	function getAuthorCountBySubmissionId($submissionId) {
		$result = $this->retrieve(
			'SELECT COUNT(*) FROM authors WHERE submission_id = ?',
			(int) $submissionId
		);

		$returner = $result->fields[0];

		$result->Close();
		return $returner;
	}

	/**
	 * Update the localized data for this object
	 * @param $author object
	 */
	function updateLocaleFields($author) {
		$this->updateDataObjectSettings(
			'author_settings',
			$author,
			array(
				'author_id' => $author->getId()
			)
		);
	}

	/**
	 * Internal function to return an Author object from a row.
	 * @param $row array
	 * @return Author
	 */
	function _fromRow($row) {
		$author = $this->newDataObject();
		$author->setId($row['author_id']);
		$author->setSubmissionId($row['submission_id']);
		$author->setFirstName($row['first_name']);
		$author->setMiddleName($row['middle_name']);
		$author->setLastName($row['last_name']);
		$author->setSuffix($row['suffix']);
		$author->setCountry($row['country']);
		$author->setEmail($row['email']);
		$author->setUrl($row['url']);
		$author->setUserGroupId($row['user_group_id']);
		$author->setPrimaryContact($row['primary_contact']);
		$author->setSequence($row['seq']);
		$author->_setShowTitle($row['show_title']); // Dependent

		$this->getDataObjectSettings('author_settings', 'author_id', $row['author_id'], $author);

		HookRegistry::call('AuthorDAO::_fromRow', array(&$author, &$row));
		return $author;
	}

	/**
	 * Internal function to return an Author object from a row. Simplified
	 * not to include object settings.
	 * @param $row array
	 * @return Author
	 */
	function _returnSimpleAuthorFromRow($row) {
		$author = $this->newDataObject();
		$author->setId($row['author_id']);
		$author->setSubmissionId($row['submission_id']);
		$author->setFirstName($row['first_name']);
		$author->setMiddleName($row['middle_name']);
		$author->setLastName($row['last_name']);
		$author->setSuffix($row['suffix']);
		$author->setCountry($row['country']);
		$author->setEmail($row['email']);
		$author->setUrl($row['url']);
		$author->setUserGroupId($row['user_group_id']);
		$author->setPrimaryContact($row['primary_contact']);
		$author->setSequence($row['seq']);

		$author->setAffiliation($row['affiliation_l'], $row['locale']);
		$author->setAffiliation($row['affiliation_pl'], $row['primary_locale']);

		HookRegistry::call('AuthorDAO::_returnSimpleAuthorFromRow', array(&$author, &$row));
		return $author;
	}

	/**
	 * Get a new data object
	 * @return DataObject
	 */
	function newDataObject() {
		assert(false); // Should be overridden by child classes
	}

	/**
	 * Get field names for which data is localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('biography', 'competingInterests', 'affiliation');
	}

	/**
	 * Insert a new Author.
	 * @param $author Author
	 */
	function insertObject($author) {
		// Set author sequence to end of author list
		if(!$author->getSequence()) {
			$authorCount = $this->getAuthorCountBySubmissionId($author->getSubmissionId());
			$author->setSequence($authorCount + 1);
		}
		// Reset primary contact for submission to this author if applicable
		if ($author->getPrimaryContact()) {
			$this->resetPrimaryContact($author->getId(), $author->getSubmissionId());
		}

		$this->update(
				'INSERT INTO authors
				(submission_id, first_name, middle_name, last_name, suffix, country, email, url, user_group_id, primary_contact, seq)
				VALUES
				(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				array(
						(int) $author->getSubmissionId(),
						$author->getFirstName(),
						$author->getMiddleName() . '', // make non-null
						$author->getLastName(),
						$author->getSuffix() . '',
						$author->getCountry(),
						$author->getEmail(),
						$author->getUrl(),
						(int) $author->getUserGroupId(),
						(int) $author->getPrimaryContact(),
						(float) $author->getSequence()
				)
		);

		$author->setId($this->getInsertId());
		$this->updateLocaleFields($author);

		return $author->getId();
	}

	/**
	 * Update an existing Author.
	 * @param $author Author
	 */
	function updateObject($author) {
		// Reset primary contact for submission to this author if applicable
		if ($author->getPrimaryContact()) {
			$this->resetPrimaryContact($author->getId(), $author->getSubmissionId());
		}
		$returner = $this->update(
				'UPDATE	authors
				SET	first_name = ?,
					middle_name = ?,
					last_name = ?,
					suffix = ?,
					country = ?,
					email = ?,
					url = ?,
					user_group_id = ?,
					primary_contact = ?,
					seq = ?
				WHERE	author_id = ?',
				array(
						$author->getFirstName(),
						$author->getMiddleName() . '', // make non-null
						$author->getLastName(),
						$author->getSuffix() . '',
						$author->getCountry(),
						$author->getEmail(),
						$author->getUrl(),
						(int) $author->getUserGroupId(),
						(int) $author->getPrimaryContact(),
						(float) $author->getSequence(),
						(int) $author->getId()
				)
		);
		$this->updateLocaleFields($author);
		return $returner;
	}

	/**
	 * Delete an Author.
	 * @param $author Author
	 */
	function deleteObject($author) {
		return $this->deleteById($author->getId());
	}

	/**
	 * Delete an author by ID.
	 * @param $authorId int
	 * @param $submissionId int optional
	 */
	function deleteById($authorId, $submissionId = null) {
		$params = array((int) $authorId);
		if ($submissionId) $params[] = (int) $submissionId;
		$returner = $this->update(
			'DELETE FROM authors WHERE author_id = ?' .
			($submissionId?' AND submission_id = ?':''),
			$params
		);
		if ($returner) $this->update('DELETE FROM author_settings WHERE author_id = ?', array((int) $authorId));

		return $returner;
	}

	/**
	 * Sequentially renumber a submission's authors in their sequence order.
	 * @param $submissionId int
	 */
	function resequenceAuthors($submissionId) {
		$result = $this->retrieve(
			'SELECT author_id FROM authors WHERE submission_id = ? ORDER BY seq',
			(int) $submissionId
		);

		for ($i=1; !$result->EOF; $i++) {
			list($authorId) = $result->fields;
			$this->update(
				'UPDATE authors SET seq = ? WHERE author_id = ?',
				array(
					$i,
					$authorId
				)
			);

			$result->MoveNext();
		}
		$result->Close();
	}

	/**
	 * Retrieve the primary author for a submission.
	 * @param $submissionId int
	 * @return Author
	 */
	function getPrimaryContact($submissionId) {
		$result = $this->retrieve(
			'SELECT a.*, ug.show_title
				FROM authors a
			JOIN user_groups ug ON (a.user_group_id=ug.user_group_id)
			WHERE submission_id = ? AND primary_contact = 1',
			(int) $submissionId
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Remove other primary contacts from a submission and set to authorId
	 * @param $authorId int
	 * @param $submissionId int
	 */
	function resetPrimaryContact($authorId, $submissionId) {
		$this->update(
			'UPDATE authors SET primary_contact = 0 WHERE primary_contact = 1 AND submission_id = ?',
			(int) $submissionId
		);
		$this->update(
			'UPDATE authors SET primary_contact = 1 WHERE author_id = ? AND submission_id = ?',
			array((int) $authorId, (int) $submissionId)
		);
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
		$authors = $this->getBySubmissionId($submissionId);
		foreach ($authors as $author) {
			$this->deleteObject($author);
		}
	}
}

?>
