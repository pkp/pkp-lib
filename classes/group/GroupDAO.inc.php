<?php

/**
 * @file classes/group/GroupDAO.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GroupDAO
 * @ingroup group
 * @see Group
 *
 * @brief Operations for retrieving and modifying Group objects.
 */


import ('lib.pkp.classes.group.Group');

class GroupDAO extends DAO {
	/**
	 * Constructor
	 */
	function GroupDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve a group by ID.
	 * @param $groupId int
	 * @param $assocType int optional
	 * @param $assocId int optional
	 * @return Group
	 */
	function &getById($groupId, $assocType = null, $assocId = null) {
		$params = array((int) $groupId);
		if ($assocType !== null) {
			$params[] = (int) $assocType;
			$params[] = (int) $assocId;
		}
		$result =& $this->retrieve(
			'SELECT * FROM groups WHERE group_id = ?' . ($assocType !== null?' AND assoc_type = ? AND assoc_id = ?':''), $params
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_returnGroupFromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		unset($result);
		return $returner;
	}

	function getGroup($groupId, $assocType = null, $assocId = null) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		$returner =& $this->getById($groupId, $assocType, $assocId);
		return $returner;
	}

	/**
	 * Get all groups for a given context.
	 * @param $assocType int
	 * @param $assocId int
	 * @param $context int (optional)
	 * @param $rangeInfo object RangeInfo object (optional)
	 * @return array
	 */
	function &getGroups($assocType, $assocId, $context = null, $rangeInfo = null) {
		$params = array((int) $assocType, (int) $assocId);
		if ($context !== null) $params[] = (int) $context;

		$result =& $this->retrieveRange(
			'SELECT * FROM groups WHERE assoc_type = ? AND assoc_id = ?' . ($context!==null?' AND context = ?':'') . ' ORDER BY context, seq',
			$params, $rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnGroupFromRow', array('id'));
		return $returner;
	}

	/**
	 * Get the list of fields for which locale data is stored.
	 * @return array
	 */
	function getLocaleFieldNames() {
		return parent::getLocaleFieldNames() + array('title');
	}

	/**
	 * Instantiate a new DataObject.
	 * @return PKPGroup
	 */
	function newDataObject() {
		return new Group();
	}

	/**
	 * Internal function to return a Group object from a row.
	 * @param $row array
	 * @return Group
	 */
	function &_returnGroupFromRow(&$row) {
		$group = $this->newDataObject();
		$group->setId($row['group_id']);
		$group->setAboutDisplayed($row['about_displayed']);
		$group->setPublishEmail($row['publish_email']);
		$group->setSequence($row['seq']);
		$group->setContext($row['context']);
		$group->setAssocType($row['assoc_type']);
		$group->setAssocId($row['assoc_id']);
		$this->getDataObjectSettings('group_settings', 'group_id', $row['group_id'], $group);

		HookRegistry::call('GroupDAO::_returnGroupFromRow', array(&$group, &$row));

		return $group;
	}

	/**
	 * Update the settings for this object
	 * @param $group object
	 */
	function updateLocaleFields(&$group) {
		$this->updateDataObjectSettings('group_settings', $group, array(
			'group_id' => $group->getId()
		));
	}

	/**
	 * Insert a new board group.
	 * @param $group Group
	 */
	function insertGroup(&$group) {
		$this->update(
			'INSERT INTO groups
				(seq, assoc_type, assoc_id, about_displayed, context, publish_email)
				VALUES
				(?, ?, ?, ?, ?, ?)',
			array(
				(int) $group->getSequence(),
				(int) $group->getAssocType(),
				(int) $group->getAssocId(),
				(int) $group->getAboutDisplayed(),
				(int) $group->getContext(),
				(int) $group->getPublishEmail()
			)
		);

		$group->setId($this->getInsertGroupId());
		$this->updateLocaleFields($group);
		return $group->getId();
	}

	/**
	 * Update an existing board group.
	 * @param $group Group
	 */
	function updateObject(&$group) {
		$returner = $this->update(
			'UPDATE groups
				SET	seq = ?,
					assoc_type = ?,
					assoc_id = ?,
					about_displayed = ?,
					context = ?,
					publish_email = ?
				WHERE	group_id = ?',
			array(
				(float) $group->getSequence(),
				(int) $group->getAssocType(),
				(int) $group->getAssocId(),
				(int) $group->getAboutDisplayed(),
				(int) $group->getContext(),
				(int) $group->getPublishEmail(),
				(int) $group->getId()
			)
		);
		$this->updateLocaleFields($group);
		return $returner;
	}

	function updateGroup(&$group) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->updateObject($group);
	}

	/**
	 * Delete a board group, including membership info
	 * @param $group Group
	 */
	function deleteObject(&$group) {
		return $this->deleteGroupById($group->getId());
	}

	function deleteGroup(&$group) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->deleteObject($group);
	}

	/**
	 * Delete a board group, including membership info
	 * @param $groupId int
	 */
	function deleteGroupById($groupId) {
		$groupMembershipDao =& DAORegistry::getDAO('GroupMembershipDAO');
		$groupMembershipDao->deleteMembershipByGroupId($groupId);
		$this->update('DELETE FROM group_settings WHERE group_id = ?', $groupId);
		return $this->update('DELETE FROM groups WHERE group_id = ?', $groupId);
	}

	/**
	 * Delete board groups by assoc ID, including membership info
	 * @param $assocType int
	 * @param $assocId int
	 */
	function deleteGroupsByAssocId($assocType, $assocId) {
		$groups =& $this->getGroups($assocType, $assocId);
		while ($group =& $groups->next()) {
			$this->deleteObject($group);
			unset($group);
		}
	}

	/**
	 * Sequentially renumber board groups in their sequence order, optionally by assoc info.
	 * @param $assocType int
	 * @param $assocId int
	 */
	function resequenceGroups($assocType = null, $assocId = null) {
		if ($assocType !== null) $params = array((int) $assocType, (int) $assocId);
		else $params = array();
		$result =& $this->retrieve(
			'SELECT group_id FROM groups' .
			($assocType !== null?' WHERE assoc_type = ? AND assoc_id = ?':'') .
			' ORDER BY seq',
			$params
		);

		for ($i=1; !$result->EOF; $i++) {
			list($groupId) = $result->fields;
			$this->update(
				'UPDATE groups SET seq = ? WHERE group_id = ?',
				array(
					$i,
					$groupId
				)
			);

			$result->MoveNext();
		}

		$result->Close();
		unset($result);
	}

	/**
	 * Get the ID of the last inserted board group.
	 * @return int
	 */
	function getInsertGroupId() {
		return $this->getInsertId('groups', 'group_id');
	}
}

?>
