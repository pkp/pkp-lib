<?php

/**
 * @file classes/user/PKPUserDAO.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPUserDAO
 * @ingroup user
 * @see User
 *
 * @brief Operations for retrieving and modifying User objects.
 */

import('lib.pkp.classes.user.PKPUser');

/* These constants are used user-selectable search fields. */
define('USER_FIELD_USERID', 'user_id');
define('USER_FIELD_USERNAME', 'username');
define('USER_FIELD_EMAIL', 'email');
define('USER_FIELD_URL', 'url');
define('USER_FIELD_INTERESTS', 'interests');
define('USER_FIELD_AFFILIATION', 'affiliation');
define('USER_FIELD_NONE', null);

class PKPUserDAO extends DAO {

	/**
	 * Construct a new User object.
	 * @return PKPUser
	 */
	function newDataObject() {
		return new PKPUser();
	}

	/**
	 * Retrieve a user by ID.
	 * @param $userId int
	 * @param $allowDisabled boolean
	 * @return PKPUser
	 */
	function getById($userId, $allowDisabled = true) {
		$result = $this->retrieve(
			'SELECT * FROM users WHERE user_id = ?' . ($allowDisabled?'':' AND disabled = 0'),
			array((int) $userId)
		);

		$user = null;
		if ($result->RecordCount() != 0) {
			$user =& $this->_returnUserFromRowWithData($result->GetRowAssoc(false));
		}
		$result->Close();
		return $user;
	}

	/**
	 * Retrieve a user by username.
	 * @param $username string
	 * @param $allowDisabled boolean
	 * @return PKPUser
	 */
	function &getByUsername($username, $allowDisabled = true) {
		$result = $this->retrieve(
			'SELECT * FROM users WHERE username = ?' . ($allowDisabled?'':' AND disabled = 0'),
			array($username)
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_returnUserFromRowWithData($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve a user by setting.
	 * @param $settingName string
	 * @param $settingValue string
	 * @param $allowDisabled boolean
	 * @return PKPUser
	 */
	function getBySetting($settingName, $settingValue, $allowDisabled = true) {
		$result = $this->retrieve(
			'SELECT u.* FROM users u JOIN user_settings us ON (u.user_id = us.user_id) WHERE us.setting_name = ? AND us.setting_value = ?' . ($allowDisabled?'':' AND u.disabled = 0'),
			array($settingName, $settingValue)
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_returnUserFromRowWithData($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Get the user by the TDL ID (implicit authentication).
	 * @param $authstr string
	 * @param $allowDisabled boolean
	 * @return object User
	 */
	function &getUserByAuthStr($authstr, $allowDisabled = true) {
		$result = $this->retrieve(
			'SELECT * FROM users WHERE auth_str = ?' . ($allowDisabled?'':' AND disabled = 0'),
			array($authstr)
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_returnUserFromRowWithData($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve a user by email address.
	 * @param $email string
	 * @param $allowDisabled boolean
	 * @return PKPUser
	 */
	function &getUserByEmail($email, $allowDisabled = true) {
		$result = $this->retrieve(
			'SELECT * FROM users WHERE email = ?' . ($allowDisabled?'':' AND disabled = 0'),
			array($email)
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_returnUserFromRowWithData($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve a user by username and (encrypted) password.
	 * @param $username string
	 * @param $password string encrypted password
	 * @param $allowDisabled boolean
	 * @return PKPUser
	 */
	function &getUserByCredentials($username, $password, $allowDisabled = true) {
		$result = $this->retrieve(
			'SELECT * FROM users WHERE username = ? AND password = ?' . ($allowDisabled?'':' AND disabled = 0'),
			array($username, $password)
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_returnUserFromRowWithData($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve a list of all reviewers assigned to a submission.
	 * @param $contextId int
	 * @param $submissionId int
	 * @param $round int
	 * @return DAOResultFactory containing matching Users
	 */
	function getReviewersForSubmission($contextId, $submissionId, $round) {
		$params = array(
			(int) $contextId,
			ROLE_ID_REVIEWER,
			(int) $submissionId,
			(int) $round
		);
		$params = array_merge($this->getFetchParameters(), $params);

		$result = $this->retrieve(
			'SELECT	u.* ,
			' . $this->getFetchColumns() . '
			FROM	users u
			LEFT JOIN user_user_groups uug ON (uug.user_id = u.user_id)
			LEFT JOIN user_groups ug ON (ug.user_group_id = uug.user_group_id)
			LEFT JOIN review_assignments r ON (r.reviewer_id = u.user_id)
			' . $this->getFetchJoins() . '
			WHERE	ug.context_id = ? AND
			ug.role_id = ? AND
			r.submission_id = ? AND
			r.round = ?
			' . $this->getOrderBy(),
			$params
		);

		return new DAOResultFactory($result, $this, '_returnUserFromRowWithData');
	}

	/**
	 * Retrieve a list of all reviewers not assigned to the specified submission.
	 * @param $contextId int
	 * @param $submissionId int
	 * @param $reviewRound ReviewRound
	 * @param $name string
	 * @return array matching Users
	 */
	function getReviewersNotAssignedToSubmission($contextId, $submissionId, &$reviewRound, $name = '') {
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');

		$params = array(
			(int) $contextId,
			ROLE_ID_REVIEWER,
			(int) $reviewRound->getStageId(),
		);
		$params = array_merge($params, $this->getFetchParameters());
		$params[] = (int) $submissionId;
		$params[] = (int) $reviewRound->getId();
		if (!empty($name)) {
			$nameSearchJoins = 'LEFT JOIN user_settings usgs ON (u.user_id = usgs.user_id AND usgs.setting_name = \'' . IDENTITY_SETTING_GIVENNAME .'\')
				LEFT JOIN user_settings usfs ON (u.user_id = usfs.user_id AND usfs.setting_name = \'' . IDENTITY_SETTING_FAMILYNAME .'\')';
			$params[] = $params[] = $params[] = $params[] = "%$name%";
		}

		$result = $this->retrieve(
			'SELECT	DISTINCT u.*,
			' . $this->getFetchColumns() .'
			FROM	users u
			JOIN user_user_groups uug ON (uug.user_id = u.user_id)
			JOIN user_groups ug ON (ug.user_group_id = uug.user_group_id AND ug.context_id = ? AND ug.role_id = ?)
			JOIN user_group_stage ugs ON (ugs.user_group_id = ug.user_group_id AND ugs.stage_id = ?)' .
			(!empty($name) ? $nameSearchJoins : '') .'
			' . $this->getFetchJoins() . '
			WHERE 0=(SELECT COUNT(r.reviewer_id)
				FROM review_assignments r
				WHERE r.submission_id = ? AND r.reviewer_id = u.user_id AND r.review_round_id = ?)' .
			(!empty($name) ?' AND (usgs.setting_value LIKE ? OR usfs.setting_value LIKE ? OR username LIKE ? OR email LIKE ?)' : '') .'
			' .$this->getOrderBy(),
			$params
		);
		return new DAOResultFactory($result, $this, '_returnUserFromRowWithData');
	}

	/**

	 * Return a user object from a DB row, including dependent data and reviewer stats.
	 * @param $row array
	 * @return User
	 */
	function _returnUserFromRowWithReviewerStats($row) {
		$user = $this->_returnUserFromRowWithData($row, false);
		$user->setData('lastAssigned', $row['last_assigned']);
		$user->setData('incompleteCount', (int) $row['incomplete_count']);
		$user->setData('completeCount', (int) $row['complete_count']);
		$user->setData('declinedCount', (int) $row['declined_count']);
		$user->setData('averageTime', (int) $row['average_time']);

		// 0 values should return null. They represent a reviewer with no ratings
		if ($row['reviewer_rating']) {
			$user->setData('reviewerRating', max(1, round($row['reviewer_rating'])));
		}

		HookRegistry::call('UserDAO::_returnUserFromRowWithReviewerStats', array(&$user, &$row));

		return $user;
	}

	/**
	 * Create and return a complete PKPUser object from a given row.
	 * @param $row array
	 * @param $callHook boolean
	 * @return PKPUser
	 */
	function &_returnUserFromRowWithData($row, $callHook = true) {
		$user =& $this->_returnUserFromRow($row, false);
		$this->getDataObjectSettings('user_settings', 'user_id', $row['user_id'], $user);

		if (isset($row['review_id'])) $user->review_id = $row['review_id'];
		HookRegistry::call('UserDAO::_returnUserFromRowWithData', array(&$user, &$row));

		return $user;
	}

	/**
	 * Internal function to return a User object from a row.
	 * @param $row array
	 * @param $callHook boolean
	 * @return PKPUser
	 */
	function &_returnUserFromRow($row, $callHook = true) {
		$user = $this->newDataObject();
		$user->setId($row['user_id']);
		$user->setUsername($row['username']);
		$user->setPassword($row['password']);
		$user->setEmail($row['email']);
		$user->setUrl($row['url']);
		$user->setPhone($row['phone']);
		$user->setMailingAddress($row['mailing_address']);
		$user->setBillingAddress($row['billing_address']);
		$user->setCountry($row['country']);
		$user->setLocales(isset($row['locales']) && !empty($row['locales']) ? explode(':', $row['locales']) : array());
		$user->setDateLastEmail($this->datetimeFromDB($row['date_last_email']));
		$user->setDateRegistered($this->datetimeFromDB($row['date_registered']));
		$user->setDateValidated($this->datetimeFromDB($row['date_validated']));
		$user->setDateLastLogin($this->datetimeFromDB($row['date_last_login']));
		$user->setMustChangePassword($row['must_change_password']);
		$user->setDisabled($row['disabled']);
		$user->setDisabledReason($row['disabled_reason']);
		$user->setAuthId($row['auth_id']);
		$user->setAuthStr($row['auth_str']);
		$user->setInlineHelp($row['inline_help']);
		$user->setGossip($row['gossip']);

		if ($callHook) HookRegistry::call('UserDAO::_returnUserFromRow', array(&$user, &$row));

		return $user;
	}

	/**
	 * Insert a new user.
	 * @param $user User
	 */
	function insertObject($user) {
		if ($user->getDateRegistered() == null) {
			$user->setDateRegistered(Core::getCurrentDate());
		}
		if ($user->getDateLastLogin() == null) {
			$user->setDateLastLogin(Core::getCurrentDate());
		}
		$this->update(
			sprintf('INSERT INTO users
				(username, password, email, url, phone, mailing_address, billing_address, country, locales, date_last_email, date_registered, date_validated, date_last_login, must_change_password, disabled, disabled_reason, auth_id, auth_str, inline_help, gossip)
				VALUES
				(?, ?, ?, ?, ?, ?, ?, ?, ?, %s, %s, %s, %s, ?, ?, ?, ?, ?, ?, ?)',
				$this->datetimeToDB($user->getDateLastEmail()), $this->datetimeToDB($user->getDateRegistered()), $this->datetimeToDB($user->getDateValidated()), $this->datetimeToDB($user->getDateLastLogin())),
			array(
				$user->getUsername(),
				$user->getPassword(),
				$user->getEmail(),
				$user->getUrl(),
				$user->getPhone(),
				$user->getMailingAddress(),
				$user->getBillingAddress(),
				$user->getCountry(),
				join(':', $user->getLocales()),
				$user->getMustChangePassword() ? 1 : 0,
				$user->getDisabled() ? 1 : 0,
				$user->getDisabledReason(),
				$user->getAuthId()=='' ? null : (int) $user->getAuthId(),
				$user->getAuthStr(),
				(int) $user->getInlineHelp(),
				$user->getGossip(),
			)
		);

		$user->setId($this->getInsertId());
		$this->updateLocaleFields($user);
		return $user->getId();
	}

	/**
	 * @copydoc DAO::getLocaleFieldNames
	 */
	function getLocaleFieldNames() {
		return array('biography', 'signature', 'affiliation',
			IDENTITY_SETTING_GIVENNAME, IDENTITY_SETTING_FAMILYNAME, 'preferredPublicName');
	}

	/**
	 * @copydoc DAO::getAdditionalFieldNames()
	 */
	function getAdditionalFieldNames() {
		return array_merge(parent::getAdditionalFieldNames(), array(
			'orcid',
			'apiKey',
			'apiKeyEnabled',
		));
	}

	/**
	 * @copydoc DAO::updateLocaleFields
	 */
	function updateLocaleFields($user) {
		$this->updateDataObjectSettings('user_settings', $user, array(
			'user_id' => (int) $user->getId()
		));
	}

	/**
	 * Update an existing user.
	 * @param $user PKPUser
	 */
	function updateObject($user) {
		if ($user->getDateLastLogin() == null) {
			$user->setDateLastLogin(Core::getCurrentDate());
		}

		$this->updateLocaleFields($user);

		return $this->update(
			sprintf('UPDATE	users
				SET	username = ?,
					password = ?,
					email = ?,
					url = ?,
					phone = ?,
					mailing_address = ?,
					billing_address = ?,
					country = ?,
					locales = ?,
					date_last_email = %s,
					date_validated = %s,
					date_last_login = %s,
					must_change_password = ?,
					disabled = ?,
					disabled_reason = ?,
					auth_id = ?,
					auth_str = ?,
					inline_help = ?,
					gossip = ?
				WHERE	user_id = ?',
				$this->datetimeToDB($user->getDateLastEmail()), $this->datetimeToDB($user->getDateValidated()), $this->datetimeToDB($user->getDateLastLogin())),
			array(
				$user->getUsername(),
				$user->getPassword(),
				$user->getEmail(),
				$user->getUrl(),
				$user->getPhone(),
				$user->getMailingAddress(),
				$user->getBillingAddress(),
				$user->getCountry(),
				join(':', $user->getLocales()),
				$user->getMustChangePassword() ? 1 : 0,
				$user->getDisabled() ? 1 : 0,
				$user->getDisabledReason(),
				$user->getAuthId()=='' ? null : (int) $user->getAuthId(),
				$user->getAuthStr(),
				(int) $user->getInlineHelp(),
				$user->getGossip(),
				(int) $user->getId(),
			)
		);
	}

	/**
	 * Delete a user.
	 * @param $user User
	 */
	function deleteObject($user) {
		return $this->deleteUserById($user->getId());
	}

	/**
	 * Delete a user by ID.
	 * @param $userId int
	 */
	function deleteUserById($userId) {
		$this->update('DELETE FROM user_settings WHERE user_id = ?', array((int) $userId));
		return $this->update('DELETE FROM users WHERE user_id = ?', array((int) $userId));
	}

	/**
	 * Retrieve a user's name.
	 * @param $userId int
	 * @param $allowDisabled boolean
	 * @return string
	 */
	function getUserFullName($userId, $allowDisabled = true) {
		$user = $this->getById($userId, $allowDisabled);
		return $user->getFullName();
	}

	/**
	 * Retrieve a user's email address.
	 * @param $userId int
	 * @param $allowDisabled boolean
	 * @return string
	 */
	function getUserEmail($userId, $allowDisabled = true) {
		$result = $this->retrieve(
			'SELECT email FROM users WHERE user_id = ?' . ($allowDisabled?'':' AND disabled = 0'),
			array((int) $userId)
		);

		if($result->RecordCount() == 0) {
			$returner = false;
		} else {
			$returner = $result->fields[0];
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve an array of users with no role defined.
	 * @param $allowDisabled boolean
	 * @param $dbResultRange object The desired range of results to return
	 * @return DAOResultFactory
	 */
	function getUsersWithNoRole($allowDisabled = true, $dbResultRange = null) {
		$sql = 'SELECT u.*,
			' . $this->getFetchColumns() . '
			FROM users u
			' . $this->getFetchJoins() . '
			LEFT JOIN roles r ON u.user_id=r.user_id
			WHERE r.role_id IS NULL';

		$orderSql = $this->getOrderBy(); // FIXME Add "sort field" parameter?
		$params = $this->getFetchParameters();
		$result = $this->retrieveRange($sql . ($allowDisabled?'':' AND u.disabled = 0') . $orderSql, $params, $dbResultRange);

		return new DAOResultFactory($result, $this, '_returnUserFromRowWithData');
	}

	/**
	 * Check if a user exists with the specified user ID.
	 * @param $userId int
	 * @param $allowDisabled boolean
	 * @return boolean
	 */
	function userExistsById($userId, $allowDisabled = true) {
		$result = $this->retrieve(
			'SELECT COUNT(*) FROM users WHERE user_id = ?' . ($allowDisabled?'':' AND disabled = 0'),
			array((int) $userId)
		);
		$returner = isset($result->fields[0]) && $result->fields[0] != 0 ? true : false;

		$result->Close();
		return $returner;
	}

	/**
	 * Check if a user exists with the specified username.
	 * @param $username string
	 * @param $userId int optional, ignore matches with this user ID
	 * @param $allowDisabled boolean
	 * @return boolean
	 */
	function userExistsByUsername($username, $userId = null, $allowDisabled = true) {
		$result = $this->retrieve(
			'SELECT COUNT(*) FROM users WHERE username = ?' . (isset($userId) ? ' AND user_id != ?' : '') . ($allowDisabled?'':' AND disabled = 0'),
			isset($userId) ? array($username, (int) $userId) : array($username)
		);
		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;

		$result->Close();
		return $returner;
	}

	/**
	 * Check if a user exists with the specified email address.
	 * @param $email string
	 * @param $userId int optional, ignore matches with this user ID
	 * @param $allowDisabled boolean
	 * @return boolean
	 */
	function userExistsByEmail($email, $userId = null, $allowDisabled = true) {
		$result = $this->retrieve(
			'SELECT COUNT(*) FROM users WHERE email = ?' . (isset($userId) ? ' AND user_id != ?' : '') . ($allowDisabled?'':' AND disabled = 0'),
			isset($userId) ? array($email, (int) $userId) : array($email)
		);
		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;

		$result->Close();
		return $returner;
	}

	/**
	 * Get the ID of the last inserted user.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('users', 'user_id');
	}

	/**
	 * Return a list of extra parameters to bind to the user fetch queries.
	 * @return array
	 */
	function getFetchParameters() {
		$locale = AppLocale::getLocale();
		// the users register for the site, thus
		// the site primary locale should be the default locale
		$site = Application::getRequest()->getSite();
		$primaryLocale = $site->getPrimaryLocale();
		return array(
			IDENTITY_SETTING_GIVENNAME, $locale,
			IDENTITY_SETTING_GIVENNAME, $primaryLocale,
			IDENTITY_SETTING_FAMILYNAME, $locale,
			IDENTITY_SETTING_FAMILYNAME, $primaryLocale,
		);
	}

	/**
	 * Return a SQL snippet of extra columns to fetch during user fetch queries.
	 * @return string
	 */
	function getFetchColumns() {
		return 'COALESCE(ugl.setting_value, ugpl.setting_value) AS user_given,
			CASE WHEN ugl.setting_value <> \'\' THEN ufl.setting_value ELSE ufpl.setting_value END AS user_family';
	}

	/**
	 * Return a SQL snippet of extra joins to include during user fetch queries.
	 * @return string
	 */
	function getFetchJoins() {
		return 'LEFT JOIN user_settings ugl ON (u.user_id = ugl.user_id AND ugl.setting_name = ? AND ugl.locale = ?)
			LEFT JOIN user_settings ugpl ON (u.user_id = ugpl.user_id AND ugpl.setting_name = ? AND ugpl.locale = ?)
			LEFT JOIN user_settings ufl ON (u.user_id = ufl.user_id AND ufl.setting_name = ? AND ufl.locale = ?)
			LEFT JOIN user_settings ufpl ON (u.user_id = ufpl.user_id AND ufpl.setting_name = ? AND ufpl.locale = ?)';
	}

	/**
	 * Return a default sorting.
	 * @return string
	 */
	function getOrderBy() {
		return 'ORDER BY user_family, user_given';
	}

}


