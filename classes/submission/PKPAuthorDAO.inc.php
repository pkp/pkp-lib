<?php

/**
 * @file classes/submission/PKPAuthorDAO.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorDAO
 * @ingroup submission
 * @see PKPAuthor
 *
 * @brief Operations for retrieving and modifying PKPAuthor objects.
 */

import('lib.pkp.classes.submission.PKPAuthor');
import('lib.pkp.classes.submission.ISubmissionVersionedDAO');
import('lib.pkp.classes.submission.SubmissionVersionedDAO');

abstract class PKPAuthorDAO extends SubmissionVersionedDAO implements ISubmissionVersionedDAO {

	/**
	 * Retrieve an author by ID.
	 * @param $authorId int Author ID
	 * @param $submissionId int Optional submission ID to correlate the author against.
	 * @param $version int
	 * @return Author
	 */
	function getById($authorId, $submissionId = null) {
		$params = array((int) $authorId);
		if ($submissionId !== null) $params[] = (int) $submissionId;

		$result = $this->retrieve(
			'SELECT DISTINCT a.*, ug.show_title, s.locale
			FROM	authors a
				JOIN user_groups ug ON (a.user_group_id=ug.user_group_id)
				JOIN submissions s ON (s.submission_id = a.submission_id)
				LEFT JOIN author_settings au ON (au.author_id = a.author_id)
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
	 * @param $submissionId int Submission ID.
	 * @param $sortByAuthorId bool Use author Ids as indexes in the array
	 * @param $useIncludeInBrowse bool Whether to limit to just include_in_browse authors
	 * @param $version int
	 * @return array Authors ordered by sequence
	 */
	function getBySubmissionId($submissionId, $sortByAuthorId = false, $useIncludeInBrowse = false, $submissionVersion = null) {
		$authors = array();
		$params = array((int) $submissionId);
		if ($useIncludeInBrowse) $params[] = 1;

		if ($submissionVersion) {
			$params[] = (int) $submissionVersion;
    }

		$result = $this->retrieve(
			'SELECT	DISTINCT a.*, ug.show_title, s.locale
			FROM	authors a
				JOIN user_groups ug ON (a.user_group_id=ug.user_group_id)
				JOIN submissions s ON (s.submission_id = a.submission_id)
				LEFT JOIN author_settings au ON (au.author_id = a.author_id)
			WHERE	a.submission_id = ? ' .
			($useIncludeInBrowse ? ' AND a.include_in_browse = ?' : '')
			. ($submissionVersion ? ' AND a.submission_version = ? ' : ' AND a.is_current_submission_version = 1')
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
	 * Retrieve the number of authors assigned to a submission
	 * @param $submissionId int Submission ID.
	 * @return int
	 */
	function getAuthorCountBySubmissionId($submissionId, $submissionVersion = null) {
		$params = array((int) $submissionId);

		if ($submissionVersion) {
			$params[] = (int) $submissionVersion;
    }

		$result = $this->retrieve(
			'SELECT COUNT(*) FROM authors WHERE submission_id = ?'
			. ($submissionVersion ? ' AND submission_version = ? ' : ' AND is_current_submission_version = 1'),
			$params
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
				'author_id' => $author->getId(),
			)
		);
	}

	/**
	 * Internal function to return an Author object from a row.
	 * @param $row array
	 * @return Author
	 */
	function _fromRow($row) {
		$author = $this->newDataObject(); /** @var $author Author*/
		$author->setId($row['author_id']);
		$author->setSubmissionId($row['submission_id']);
		$author->setSubmissionLocale($row['locale']);
		$author->setCountry($row['country']);
		$author->setEmail($row['email']);
		$author->setUrl($row['url']);
		$author->setUserGroupId($row['user_group_id']);
		$author->setPrimaryContact($row['primary_contact']);
		$author->setSequence($row['seq']);
		$author->setIncludeInBrowse($row['include_in_browse']);
		$author->_setShowTitle($row['show_title']); // Dependent
		$author->setSubmissionVersion($row['submission_version']);
		$author->setPrevVerAssocId($row['prev_ver_id']);
		$author->setIsCurrentSubmissionVersion($row['is_current_submission_version']);

		$this->getDataObjectSettings('author_settings', 'author_id', $row['author_id'], $author);

		HookRegistry::call('AuthorDAO::_fromRow', array(&$author, &$row));

		return $author;
	}

	/**
	 * Get a new data object
	 * @return DataObject
	 */
	abstract function newDataObject();

	/**
	 * Get field names for which data is localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('biography', 'competingInterests', 'affiliation',
			IDENTITY_SETTING_GIVENNAME, IDENTITY_SETTING_FAMILYNAME, 'preferredPublicName');
	}

	/**
	 * @copydoc DAO::getAdditionalFieldNames()
	 */
	function getAdditionalFieldNames() {
		return array_merge(parent::getAdditionalFieldNames(), array(
			'orcid',
		));
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
			'INSERT INTO authors (
				submission_id, country, email, url, user_group_id, primary_contact, seq, include_in_browse, submission_version, prev_ver_id, is_current_submission_version
			) VALUES (
				?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
			)',
				array(
					(int) $author->getSubmissionId(),
					$author->getCountry(),
					$author->getEmail(),
					$author->getUrl(),
					(int) $author->getUserGroupId(),
					(int) $author->getPrimaryContact(),
					(float) $author->getSequence(),
					(int) $author->getIncludeInBrowse() ? 1 : 0,
					(int) $author->getSubmissionVersion(),
					(int) $author->getPrevVerAssocId() ? $author->getPrevVerAssocId() : 0,
					(int) $author->getIsCurrentSubmissionVersion(),
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
			SET	country = ?,
				email = ?,
				url = ?,
				user_group_id = ?,
				primary_contact = ?,
				seq = ?,
				include_in_browse = ?,
				submission_version = ?,
				prev_ver_id = ?,
				is_current_submission_version = ?
			WHERE	author_id = ?',
			array(
				$author->getCountry(),
				$author->getEmail(),
				$author->getUrl(),
				(int) $author->getUserGroupId(),
				(int) $author->getPrimaryContact(),
				(float) $author->getSequence(),
				(int) $author->getIncludeInBrowse() ? 1 : 0,
				(int) $author->getSubmissionVersion(),
				(int) $author->getPrevVerAssocId(),
				(int) $author->getIsCurrentSubmissionVersion(),
				(int) $author->getId()
			)
		);
		$this->updateLocaleFields($author);
		return $returner;
	}

	/**
	 * Delete an Author.
	 * @param $author Author Author object to delete.
	 */
	function deleteObject($author) {
		return $this->deleteById($author->getId());
	}

	/**
	 * Delete an author by ID.
	 * @param $authorId int Author ID
	 * @param $submissionId int Optional submission ID.
	 */
	function deleteById($authorId, $submissionId = null) {
		$params = array((int) $authorId);
		if ($submissionId) $params[] = (int) $submissionId;

		$this->update(
			'DELETE FROM authors WHERE author_id = ?' .
			($submissionId?' AND submission_id = ?':''),
			$params
		);

		$settingsParams = array((int) $authorId);
		$this->update('DELETE FROM author_settings WHERE author_id = ?', $settingsParams);
	}

	/**
	 * Sequentially renumber a submission's authors in their sequence order.
	 * @param $submissionId int Submission ID.
	 */
	function resequenceAuthors($submissionId, $submissionVersion = null) {
		$params = array((int) $submissionId);

		if ($submissionVersion) {
			$params[] = (int) $submissionVersion;
    }

		$result = $this->retrieve(
			'SELECT author_id FROM authors WHERE submission_id = ?'
			. ($submissionVersion ? ' AND submission_version = ? ' : ' AND is_current_submission_version = 1')
			.' ORDER BY seq',
			$params
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
	 * @param $submissionId int Submission ID.
	 * @return Author
	 */
	function getPrimaryContact($submissionId, $submissionVersion = null) {
		$params = array((int) $submissionId);

		if ($submissionVersion) {
			$params[] = (int) $submissionVersion;
    }

		$result = $this->retrieve(
			'SELECT a.*, ug.show_title, s.locale
			FROM authors a
				JOIN user_groups ug ON (a.user_group_id=ug.user_group_id)
				JOIN submissions s ON (s.submission_id = a.submission_id)
			WHERE a.submission_id = ?'
			. ($submissionVersion ? ' AND a.submission_version = ? ' : ' AND a.is_current_submission_version = 1')
			. ' AND a.primary_contact = 1',
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
	 * Remove other primary contacts from a submission and set to authorId
	 * @param $authorId int Author ID.
	 * @param $submissionId int Submission ID.
	 */
	function resetPrimaryContact($authorId, $submissionId, $submissionVersion = null) {
		$params = array((int) $submissionId);
		if ($submissionVersion) {
			$params[] = (int) $submissionVersion;
    }

		$this->update(
			'UPDATE authors SET primary_contact = 0
			WHERE primary_contact = 1
			AND submission_id = ?'
			. ($submissionVersion ? ' AND submission_version = ? ' : ' AND is_current_submission_version = 1'),
			$params
		);

		$params = array((int) $authorId, (int) $submissionId);

		$this->update(
			'UPDATE authors SET primary_contact = 1 WHERE author_id = ? AND submission_id = ?',
			$params
		);
	}

	/**
	 * Update author names when submisison locale changes.
	 * @param $submissionId int
	 * @param $oldLocale string
	 * @param $newLocale string
	 */
	function changeSubmissionLocale($submissionId, $oldLocale, $newLocale) {
		$authors = $this->getBySubmissionId($submissionId);
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
	function deleteBySubmissionId($submissionId, $submissionVersion = null) {
		$authors = $this->getBySubmissionId($submissionId, false, false, $submissionVersion);
		foreach ($authors as $author) {
			$this->deleteObject($author);
		}
	}

	/**
	 * Return a list of extra parameters to bind to the author fetch queries.
	 * @return array
	 */
	function getFetchParameters() {
		$locale = AppLocale::getLocale();
		return array(
			IDENTITY_SETTING_GIVENNAME, $locale,
			IDENTITY_SETTING_GIVENNAME,
			IDENTITY_SETTING_FAMILYNAME, $locale,
			IDENTITY_SETTING_FAMILYNAME,
		);
	}

	/**
	 * Return a SQL snippet of extra columns to fetch during author fetch queries.
	 * @return string
	 */
	function getFetchColumns() {
		return 'COALESCE(agl.setting_value, agpl.setting_value) AS author_given,
			CASE WHEN agl.setting_value <> \'\' THEN afl.setting_value ELSE afpl.setting_value END AS author_family';
	}

	/**
	 * Return a SQL snippet of extra joins to include during author fetch queries.
	 * @return string
	 */
	function getFetchJoins() {
		return 'LEFT JOIN author_settings agl ON (a.author_id = agl.author_id AND agl.setting_name = ? AND agl.locale = ?)
			LEFT JOIN author_settings agpl ON (a.author_id = agpl.author_id AND agpl.setting_name = ? AND agpl.locale = s.locale)
			LEFT JOIN author_settings afl ON (a.author_id = afl.author_id AND afl.setting_name = ? AND afl.locale = ?)
			LEFT JOIN author_settings afpl ON (a.author_id = afpl.author_id AND afpl.setting_name = ? AND afpl.locale = s.locale)';
	}

	/**
	 * Return a default sorting.
	 * @return string
	 */
	function getOrderBy() {
		return 'ORDER BY author_family, author_given';
	}

	function newVersion($submissionId) {
		list($oldVersion, $newVersion) = $this->provideSubmissionVersionsForNewVersion($submissionId);

		$authors = $this->getBySubmissionId($submissionId, false, false, $oldVersion);

		foreach($authors as $author) {
			/** @var $author Author */
			$authorOldId = $author->getId();

			$author->setIsCurrentSubmissionVersion(false);
			$this->updateObject($author);

			$author->setIsCurrentSubmissionVersion(true);
			$author->setSubmissionVersion($newVersion);
			$author->setPrevVerAssocId($authorOldId);

			$this->insertObject($author);
		}
	}

	function getVersioningAssocType() {
		return ASSOC_TYPE_AUTHOR;
	}

	function getMasterTableName() {
		return 'authors';
	}
}
