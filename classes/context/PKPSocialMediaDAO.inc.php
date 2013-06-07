<?php

/**
 * @file classes/context/PKPSocialMediaDAO.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSocialMediaDAO
 * @ingroup context
 * @see PKPSocialMedia
 *
 * @brief Operations for retrieving and modifying PKPSocialMedia objects.
 */

import ('lib.pkp.classes.context.PKPSocialMedia');

class PKPSocialMediaDAO extends DAO {
	/**
	 * Constructor
	 */
	function PKPSocialMediaDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve a media object by ID.
	 * @param $socialMediaId int
	 * @param $contextId int optional
	 * @return SocialMedia
	 */
	function getById($socialMediaId, $contextId = null) {
		$params = array((int) $socialMediaId);
		if ($contextId) $params[] = (int) $contextId;

		$result = $this->retrieve(
			'SELECT *
			FROM social_media
			WHERE social_media_id = ?
			' . ($contextId?' AND context_id = ?':''),
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
	 * Construct a new data object corresponding to this DAO.
	 * @return PKPSocialMedia
	 */
	function newDataObject() {
		return new PKPSocialMedia();
	}

	/**
	 * Internal function to return a SocialMedia object from a row.
	 * @param $row array
	 * @return SocialMedia
	 */
	function _fromRow($row) {
		$socialMedia = $this->newDataObject();

		$socialMedia->setId($row['social_media_id']);
		$socialMedia->setContextId($row['context_id']);
		$socialMedia->setCode($row['code']);
		$socialMedia->setIncludeInCatalog($row['include_in_catalog']);

		$this->getDataObjectSettings('social_media_settings', 'social_media_id', $row['social_media_id'], $socialMedia);

		HookRegistry::call('SocialMediaDAO::_fromRow', array(&$socialMedia, &$row));

		return $socialMedia;
	}

	/**
	 * Get the list of fields for which data can be localized.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return array('platform');
	}

	/**
	 * Update the localized fields for this object.
	 * @param $socialMedia object
	 */
	function updateLocaleFields($socialMedia) {
		$this->updateDataObjectSettings(
			'social_media_settings', $socialMedia,
			array(
				'social_media_id' => $socialMedia->getId(),
			)
		);
	}

	/**
	 * Insert a new object.
	 * @param $socialMedia SocialMedia
	 * @return int ID of the inserted link.
	 */
	function insertObject($socialMedia) {
		$this->update(
			'INSERT INTO social_media
				(context_id, code, include_in_catalog)
				VALUES
				(?, ?, ?)',
			array(
				(int) $socialMedia->getContextId(),
				$socialMedia->getCode(),
				$socialMedia->getIncludeInCatalog(),
			)
		);

		$socialMedia->setId($this->getInsertId());
		$this->updateLocaleFields($socialMedia);
		return $socialMedia->getId();
	}

	/**
	 * Update an existing link.
	 * @param $socialMedia SocialMedia
	 */
	function updateObject($socialMedia) {
		$returner = $this->update(
			'UPDATE	social_media
			SET	context_id = ?,
				code = ?,
				include_in_catalog = ?
			WHERE	social_media_id = ?',
			array(
				(int) $socialMedia->getContextId(),
				$socialMedia->getCode(),
				(int) $socialMedia->getIncludeInCatalog(),
				(int) $socialMedia->getId(),
			)
		);
		$this->updateLocaleFields($socialMedia);
		return $returner;
	}

	/**
	 * Delete an object.
	 * @param $socialMedia SocialMedia
	 */
	function deleteObject($socialMedia) {
		return $this->deleteById(
			$socialMedia->getId(),
			$socialMedia->getContextId()
		);
	}

	/**
	 * Delete an object by ID.
	 * @param $socialMediaId int
	 * @param $contextId int optional
	 */
	function deleteById($socialMediaId, $contextId = null) {
		$params = array((int) $socialMediaId);
		if ($contextId) $params[] = (int) $contextId;

		$this->update(
			'DELETE FROM social_media
			WHERE social_media_id = ?
				' . ($contextId?' AND context_id = ?':''),
			$params
		);

		if ($this->getAffectedRows()) {
			return $this->update(
				'DELETE FROM social_media_settings WHERE social_media_id = ?',
				array((int) $socialMediaId)
			);
		}
	}

	/**
	 * Delete social media items by context ID
	 * NOTE: This does not delete dependent entries. It is intended
	 * to be called only when deleting a context.
	 * @param $contextId int
	 */
	function deleteByContextId($contextId) {
		$socialMediaObjects = $this->getByContextId($contextId);
		while ($socialMedia = $socialMediaObjects->next()) {
			$this->deleteObject($socialMedia, $contextId);
		}
	}

	/**
	 * Retrieve all media objects for a context.
	 * @param $contextId int
	 * @return DAOResultFactory containing SocialMedia objects
	 */
	function getByContextId($contextId, $rangeInfo = null) {
		$params = array((int) $contextId);

		$result = $this->retrieveRange(
			'SELECT *
			FROM social_media
			WHERE context_id = ?',
			$params,
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_fromRow');
		return $returner;
	}

	/**
	 * Retrieve the media objects for a context that are set to be used in the catalog.
	 * @param int $contextId
	 * @return DAOResultFactory containing SocialMedia objects
	 */
	function getEnabledForCatalogByContextId($contextId, $rangeInfo = null) {
		$params = array((int) $contextId);

		$result = $this->retrieveRange(
			'SELECT *
			FROM social_media
			WHERE include_in_catalog = 1 AND context_id = ?',
			$params,
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_fromRow');
		return $returner;
	}

	/**
	 * Retrieve the media objects for a context that are set to be used on the context home page.
	 * @param int $contextId
	 * @return DAOResultFactory containing SocialMedia objects
	 */
	function getEnabledForContextByContextId($contextId, $rangeInfo = null) {
		$params = array((int) $contextId);

		$result = $this->retrieveRange(
			'SELECT *
			FROM social_media
			WHERE include_in_catalog = 0 AND context_id = ?',
			$params,
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_fromRow');
		return $returner;
	}

	/**
	 * Get the ID of the last inserted link.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('social_media', 'social_media_id');
	}
}
?>
