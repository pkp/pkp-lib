<?php

/**
 * @file classes/captcha/CaptchaDAO.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CaptchaDAO
 * @ingroup captcha
 * @see Captcha
 *
 * @brief Operations for retrieving and modifying Captcha keys.
 */


import('lib.pkp.classes.captcha.Captcha');

class CaptchaDAO extends DAO {
	/**
	 * Constructor
	 */
	function CaptchaDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve captchas by session id
	 * @param $userId int
	 * @return Captcha objects array
	 */
	function &getCaptchasBySessionId($sessionId) {
		$captchas = array();

		$result =& $this->retrieve(
			'SELECT * FROM captchas WHERE session_id = ?',
			array((int) $sessionId)
		);

		while (!$result->EOF) {
			$captchas[] =& $this->_returnCaptchaFromRow($result->GetRowAssoc(false));
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $captchas;
	}

	/**
	 * Retrieve expired captchas
	 * @param $lifespan int optional number of seconds a captcha should last
	 * @return Captcha objects array
	 */
	function &getExpiredCaptchas($lifespan = 86400) {
		$captchas = array();
		$threshold = time() - $lifespan;

		$result =& $this->retrieve(
			'SELECT	c.*
			FROM	captchas c
				LEFT JOIN sessions s ON (s.session_id = c.session_id)
			WHERE	s.session_id IS NULL OR
				c.date_created <= ' . $this->datetimeToDB($threshold)
		);

		while (!$result->EOF) {
			$captchas[] =& $this->_returnCaptchaFromRow($result->GetRowAssoc(false));
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $captchas;
	}

	/**
	 * Retrieve Captcha by captcha id
	 * @param $captchaId int
	 * @return Captcha object
	 */
	function &getCaptcha($captchaId) {
		$result =& $this->retrieve(
			'SELECT * FROM captchas WHERE captcha_id = ?',
			array((int) $captchaId)
		);

		$captcha = null;
		if ($result->RecordCount() != 0) {
			$captcha =& $this->_returnCaptchaFromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		unset($result);

		return $captcha;
	}

	/**
	 * Instantiate and return a new data object.
	 * @return Captcha
	 */
	function newDataObject() {
		return new Captcha();
	}

	/**
	 * Creates and returns a captcha object from a row
	 * @param $row array
	 * @return Captcha object
	 */
	function &_returnCaptchaFromRow($row) {
		$captcha = $this->newDataObject();
		$captcha->setId($row['captcha_id']);
		$captcha->setSessionId($row['session_id']);
		$captcha->setValue($row['value']);
		$captcha->setDateCreated($this->datetimeFromDB($row['date_created']));

		HookRegistry::call('CaptchaDAO::_returnCaptchaFromRow', array(&$captcha, &$row));

		return $captcha;
	}

	/**
	 * inserts a new captcha into captchas table
	 * @param Captcha object
	 * @return int ID of new captcha
	 */
	function insertCaptcha(&$captcha) {
		$captcha->setDateCreated(Core::getCurrentDate());
		$this->update(
			sprintf('INSERT INTO captchas
				(session_id, value, date_created)
				VALUES
				(?, ?, %s)',
				$this->datetimeToDB($captcha->getDateCreated())),
			array(
				(int) $captcha->getSessionId(),
				$captcha->getValue()
			)
		);

		$captcha->setId($this->getInsertCaptchaId());
		return $captcha->getId();
	}

	/**
	 * Get the ID of the last inserted captcha.
	 * @return int
	 */
	function getInsertCaptchaId() {
		return $this->getInsertId('captchas', 'captcha_id');
	}

	/**
	 * removes a captcha from captchas table
	 * @param Captcha object
	 */
	function deleteObject(&$captcha) {
		$result = $this->update(
			'DELETE FROM captchas WHERE captcha_id = ?',
			array((int) $captcha->getId())
		);
	}

	function deleteCaptcha(&$captcha) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->deleteObject($captcha);
	}

	/**
	 * updates a captcha
	 * @param Captcha object
	 */
	function updateObject(&$captcha) {
		$this->update(
			sprintf('UPDATE captchas
				SET
					session_id = ?,
					value = ?,
					date_created = %s
				WHERE captcha_id = ?',
				$this->datetimeToDB($captcha->getDateCreated())),
			array(
				(int) $captcha->getSessionId(),
				$captcha->getValue(),
				(int) $captcha->getId()
			)
		);
	}

	function updateCaptcha(&$captcha) {
		if (Config::getVar('debug', 'deprecation_warnings')) trigger_error('Deprecated function.');
		return $this->updateObject($captcha);
	}
}

?>
