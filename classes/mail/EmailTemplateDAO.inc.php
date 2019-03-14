<?php

/**
 * @file classes/mail/EmailTemplateDAO.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EmailTemplateDAO
 * @ingroup mail
 * @see EmailTemplate
 *
 * @brief Operations for retrieving and modifying Email Template objects.
 */

import('lib.pkp.classes.mail.EmailTemplate');
import('lib.pkp.classes.db.SchemaDAO');
import('classes.core.Services');

class EmailTemplateDAO extends SchemaDAO {
	/** @copydoc SchemaDAO::$schemaName */
	var $schemaName = SCHEMA_EMAIL_TEMPLATE;

	/** @copydoc SchemaDAO::$tableName */
	var $tableName = 'email_templates';

	/** @copydoc SchemaDAO::$settingsTableName */
	var $settingsTableName = 'email_templates_settings';

	/** @copydoc SchemaDAO::$primaryKeyColumn */
	var $primaryKeyColumn = 'email_id';

	/** @var array Maps schema properties for the primary table to their column names */
	var $primaryTableColumns = [
		'id' => 'email_id',
		'key' => 'email_key',
		'contextId' => 'context_id',
		'enabled' => 'enabled',
		'canDisable' => 'can_disable',
		'canEdit' => 'can_edit',
		'fromRoleId' => 'from_role_id',
		'toRoleId' => 'to_role_id',
	];

	/**
	 * @copydoc SchemaDAO::newDataObject()
	 */
	public function newDataObject() {
		return new EmailTemplate();
	}

	/**
	 * @copydoc SchemaDAO::insertObject()
	 */
	public function insertObject($object) {
		// The object contains custom template information as well as the default data.
		// Strip default data from the object before calling insertObject so that it
		// doesn't try to write this data to the email templates settings table.
		$partialObject = clone $object;
		unset($partialObject->_data['canDisable']);
		unset($partialObject->_data['canEdit']);
		unset($partialObject->_data['description']);
		unset($partialObject->_data['fromRoleId']);
		unset($partialObject->_data['toRoleId']);

		parent::insertObject($partialObject);
	}

	/**
	 * @copydoc SchemaDAO::updateObject()
	 */
	public function updateObject($object) {
		// The object contains custom template information as well as the default data.
		// Strip default data from the object before calling updateObject so that it
		// doesn't try to write this data to the email templates settings table.
		$partialObject = clone $object;
		unset($partialObject->_data['canDisable']);
		unset($partialObject->_data['canEdit']);
		unset($partialObject->_data['description']);
		unset($partialObject->_data['fromRoleId']);
		unset($partialObject->_data['toRoleId']);

		parent::updateObject($partialObject);
	}

	/**
	 * Extend SchemaDAO::_fromRow() to add data from the email template defaults
	 *
	 * @param $primaryRow array The result row from the primary table lookup
	 * @return BaseEmailTemplate
	 */
	public function _fromRow($primaryRow) {
		$emailTemplate = parent::_fromRow($primaryRow);
		$schema = Services::get('schema')->get($this->schemaName);

		$result = $this->retrieve(
			"SELECT * FROM email_templates_default_data WHERE email_key = ?",
			[$emailTemplate->getData('key')]
		);
		$props = ['subject', 'body', 'description'];
		while (!$result->EOF) {
			$settingRow = $result->getRowAssoc(false);
			foreach ($props as $prop) {
				// Don't allow default data to override custom template data
				if ($emailTemplate->getData($prop, $settingRow['locale'])) {
					continue;
				}
				$emailTemplate->setData(
					$prop,
					$this->convertFromDB(
						$settingRow[$prop],
						$schema->properties->{$prop}->type
					),
					$settingRow['locale']
				);
			}
			$result->MoveNext();
		}
		$result->Close();

		return $emailTemplate;
	}

	/**
	 * Retrieve a base email template by key.
	 * @param $emailKey string
	 * @param $contextId int
	 * @return BaseEmailTemplate
	 */
	function getBaseEmailTemplate($emailKey, $contextId) {
		$result = $this->retrieve(
			'SELECT	d.email_key,
				d.can_edit,
				d.can_disable,
				COALESCE(e.enabled, 1) AS enabled,
				e.email_id,
				e.assoc_type,
				e.assoc_id,
				d.from_role_id,
				d.to_role_id
			FROM	email_templates_default d
				LEFT JOIN email_templates e ON (d.email_key = e.email_key AND e.assoc_type = ? AND e.assoc_id = ?)
			WHERE	d.email_key = ?',
			array(Application::getContextAssocType(), (int) $contextId, $emailKey)
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_returnBaseEmailTemplateFromRow($result->GetRowAssoc(false));
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve localized email template by key.
	 * @param $emailKey string
	 * @param $contextId int Context ID
	 * @return LocaleEmailTemplate
	 */
	function getLocaleEmailTemplate($emailKey, $contextId) {
		$result = $this->retrieve(
			'SELECT	d.email_key,
				d.can_edit,
				d.can_disable,
				COALESCE(e.enabled, 1) AS enabled,
				e.email_id,
				e.assoc_type,
				e.assoc_id,
				d.from_role_id,
				d.to_role_id
			FROM	email_templates_default d
				LEFT JOIN email_templates e ON (d.email_key = e.email_key AND e.assoc_type = ? AND e.assoc_id = ?)
			WHERE	d.email_key = ?',
			array(Application::getContextAssocType(), (int) $contextId, $emailKey)
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_returnLocaleEmailTemplateFromRow($result->GetRowAssoc(false));
		} else {
			$result->Close();

			// Check to see if there's a custom email template. This is done in PHP to avoid
			// having to do a full outer join or union in SQL.
			$result = $this->retrieve(
				'SELECT	e.email_key,
					1 AS can_edit,
					1 AS can_disable,
					e.enabled,
					e.email_id,
					e.assoc_type,
					e.assoc_id,
					NULL AS from_role_id,
					NULL AS to_role_id
				FROM	email_templates e
					LEFT JOIN email_templates_default d ON (e.email_key = d.email_key)
				WHERE	d.email_key IS NULL AND
					e.assoc_type = ? AND
					e.assoc_id = ? AND
					e.email_key = ?',
				array(Application::getContextAssocType(), $contextId, $emailKey)
			);
			if ($result->RecordCount() != 0) {
				$returner = $this->_returnLocaleEmailTemplateFromRow($result->GetRowAssoc(false));
			}
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Internal function to return an email template object from a row.
	 * @param $row array
	 * @return BaseEmailTemplate
	 */
	function &_returnBaseEmailTemplateFromRow($row) {
		$emailTemplate = new BaseEmailTemplate();
		$emailTemplate->setEmailId($row['email_id']);
		$emailTemplate->setAssocType($row['assoc_type']);
		$emailTemplate->setAssocId($row['assoc_id']);
		$emailTemplate->setEmailKey($row['email_key']);
		$emailTemplate->setEnabled($row['enabled'] == null ? 1 : $row['enabled']);
		$emailTemplate->setCanDisable($row['can_disable']);
		$emailTemplate->setFromRoleId($row['from_role_id']);
		$emailTemplate->setToRoleId($row['to_role_id']);

		HookRegistry::call('EmailTemplateDAO::_returnBaseEmailTemplateFromRow', array(&$emailTemplate, &$row));

		return $emailTemplate;
	}

	/**
	 * Internal function to return an email template object from a row.
	 * @param $row array
	 * @return LocaleEmailTemplate
	 */
	function &_returnLocaleEmailTemplateFromRow($row) {
		$emailTemplate = new LocaleEmailTemplate();
		$emailTemplate->setEmailId($row['email_id']);
		$emailTemplate->setAssocType($row['assoc_type']);
		$emailTemplate->setAssocId($row['assoc_id']);
		$emailTemplate->setEmailKey($row['email_key']);
		$emailTemplate->setEnabled($row['enabled'] == null ? 1 : $row['enabled']);
		$emailTemplate->setCanDisable($row['can_disable']);
		$emailTemplate->setFromRoleId($row['from_role_id']);
		$emailTemplate->setToRoleId($row['to_role_id']);

		$emailTemplate->setCustomTemplate(false);

		if (!HookRegistry::call('EmailTemplateDAO::_returnLocaleEmailTemplateFromRow', array(&$emailTemplate, &$row))) {
			$result = $this->retrieve(
				'SELECT	dd.locale,
					dd.description,
					COALESCE(ed.subject, dd.subject) AS subject,
					COALESCE(ed.body, dd.body) AS body
				FROM	email_templates_default_data dd
					LEFT JOIN email_templates_settings ed ON (dd.email_key = ed.email_key AND dd.locale = ed.locale AND ed.assoc_type = ? AND ed.assoc_id = ?)
				WHERE	dd.email_key = ?',
				array($row['assoc_type'], $row['assoc_id'], $row['email_key'])
			);

			while (!$result->EOF) {
				$dataRow = $result->GetRowAssoc(false);
				$emailTemplate->addLocale($dataRow['locale']);
				$emailTemplate->setSubject($dataRow['locale'], $dataRow['subject']);
				$emailTemplate->setBody($dataRow['locale'], $dataRow['body']);
				$emailTemplate->setDescription($dataRow['locale'], $dataRow['description']);
				$result->MoveNext();
			}
			$result->Close();

			// Retrieve custom email contents as well; this is done in PHP to avoid
			// using a SQL outer join or union.
			$result = $this->retrieve(
				'SELECT	ed.locale,
					ed.subject,
					ed.body
				FROM	email_templates_settings ed
					LEFT JOIN email_templates_default_data dd ON (ed.email_key = dd.email_key AND dd.locale = ed.locale)
				WHERE	ed.assoc_type = ? AND
					ed.assoc_id = ? AND
					ed.email_key = ? AND
					dd.email_key IS NULL',
				array($row['assoc_type'], $row['assoc_id'], $row['email_key'])
			);

			while (!$result->EOF) {
				$dataRow = $result->GetRowAssoc(false);
				$emailTemplate->addLocale($dataRow['locale']);
				$emailTemplate->setSubject($dataRow['locale'], $dataRow['subject']);
				$emailTemplate->setBody($dataRow['locale'], $dataRow['body']);
				$result->MoveNext();

				$emailTemplate->setCustomTemplate(true);
			}

			$result->Close();
		}

		return $emailTemplate;
	}

	/**
	 * Internal function to return an email template object from a row.
	 * @param $row array
	 * @return EmailTemplate
	 */
	function &_returnEmailTemplateFromRow($row, $isCustomTemplate=null) {
		$emailTemplate = new EmailTemplate();
		$emailTemplate->setEmailId($row['email_id']);
		$emailTemplate->setAssocType($row['assoc_type']);
		$emailTemplate->setAssocId($row['assoc_id']);
		$emailTemplate->setEmailKey($row['email_key']);
		$emailTemplate->setLocale($row['locale']);
		$emailTemplate->setSubject($row['subject']);
		$emailTemplate->setBody($row['body']);
		$emailTemplate->setEnabled($row['enabled'] == null ? 1 : $row['enabled']);
		$emailTemplate->setCanDisable($row['can_disable']);
		$emailTemplate->setFromRoleId($row['from_role_id']);
		$emailTemplate->setToRoleId($row['to_role_id']);

		if ($isCustomTemplate !== null) {
			$emailTemplate->setCustomTemplate($isCustomTemplate);
		}

		HookRegistry::call('EmailTemplateDAO::_returnEmailTemplateFromRow', array(&$emailTemplate, &$row));

		return $emailTemplate;
	}

	/**
	 * Insert a new base email template.
	 * @param $emailTemplate BaseEmailTemplate
	 */
	function insertBaseEmailTemplate(&$emailTemplate) {
		return $this->update(
			'INSERT INTO email_templates
				(assoc_type, assoc_id, email_key, enabled)
				VALUES
				(?, ?, ?, ?)',
			array(
				$emailTemplate->getAssocType(),
				$emailTemplate->getAssocId(),
				$emailTemplate->getEmailKey(),
				$emailTemplate->getEnabled() == null ? 0 : 1
			)
		);
		$emailTemplate->setEmailId($this->getInsertId());
		return $emailTemplate->getEmailId();
	}

	/**
	 * Update an existing base email template.
	 * @param $emailTemplate BaseEmailTemplate
	 */
	function updateBaseEmailTemplate(&$emailTemplate) {
		return $this->update(
			'UPDATE	email_templates
			SET	enabled = ?
			WHERE	email_id = ?',
			array(
				$emailTemplate->getEnabled() == null ? 0 : 1,
				$emailTemplate->getEmailId()
			)
		);
	}

	/**
	 * Insert a new localized email template.
	 * @param $emailTemplate LocaleEmailTemplate
	 */
	function insertLocaleEmailTemplate(&$emailTemplate) {
		$this->insertBaseEmailTemplate($emailTemplate);
		return $this->updateLocaleEmailTemplateData($emailTemplate);
	}

	/**
	 * Update an existing localized email template.
	 * @param $emailTemplate LocaleEmailTemplate
	 */
	function updateLocaleEmailTemplate(&$emailTemplate) {
		$this->updateBaseEmailTemplate($emailTemplate);
		return $this->updateLocaleEmailTemplateData($emailTemplate);
	}

	/**
	 * Insert/update locale-specific email template data.
	 * @param $emailTemplate LocaleEmailTemplate
	 */
	function updateLocaleEmailTemplateData(&$emailTemplate) {
		foreach ($emailTemplate->getLocales() as $locale) {
			$result = $this->retrieve(
				'SELECT	COUNT(*)
				FROM	email_templates_settings
				WHERE	email_key = ? AND
					locale = ? AND
					assoc_type = ? AND
					assoc_id = ?',
				array(
					$emailTemplate->getEmailKey(),
					$locale,
					$emailTemplate->getAssocType(),
					$emailTemplate->getAssocId()
				)
			);

			if ($result->fields[0] == 0) {
				$this->update(
					'INSERT INTO email_templates_settings
					(email_key, locale, assoc_type, assoc_id, subject, body)
					VALUES
					(?, ?, ?, ?, ?, ?)',
					array(
						$emailTemplate->getEmailKey(),
						$locale,
						$emailTemplate->getAssocType(),
						$emailTemplate->getAssocId(),
						$emailTemplate->getSubject($locale),
						$emailTemplate->getBody($locale)
					)
				);

			} else {
				$this->update(
					'UPDATE	email_templates_settings
					SET	subject = ?,
						body = ?
					WHERE	email_key = ? AND
						locale = ? AND
						assoc_type = ? AND
						assoc_id = ?',
					array(
						$emailTemplate->getSubject($locale),
						$emailTemplate->getBody($locale),
						$emailTemplate->getEmailKey(),
						$locale,
						$emailTemplate->getAssocType(),
						$emailTemplate->getAssocId()
					)
				);
			}
			$result->Close();
		}
	}

	/**
	 * Delete an email template by key.
	 * @param $emailKey string
	 * @param $contextId int
	 * @param $assocId int
	 */
	function deleteEmailTemplateByKey($emailKey, $contextId) {
		$this->update(
			'DELETE FROM email_templates_settings WHERE email_key = ? AND assoc_type = ? AND assoc_id = ?',
			array($emailKey, Application::getContextAssocType(), (int) $contextId)
		);
		return $this->update(
			'DELETE FROM email_templates WHERE email_key = ? AND assoc_type = ? AND assoc_id = ?',
			array($emailKey, Application::getContextAssocType(), (int) $contextId)
		);
	}

	/**
	 * Get the ID of the last inserted email template.
	 * @return int
	 */
	function getInsertId() {
		return $this->_getInsertId('email_templates', 'emailId');
	}

	/**
	 * Delete all email templates for a specific journal/conference/...
	 * @param $contextId int
	 */
	function deleteEmailTemplatesByContext($contextId) {
		$this->update(
			'DELETE FROM email_templates_settings WHERE assoc_type = ? AND assoc_id = ?',
			array(Application::getContextAssocType(), (int) $contextId)
		);
		return $this->update(
			'DELETE FROM email_templates WHERE assoc_type = ? AND assoc_id = ?',
			array(Application::getContextAssocType(), (int) $contextId)
		);
	}

	/**
	 * Delete all email templates for a specific locale.
	 * @param $locale string
	 */
	function deleteEmailTemplatesByLocale($locale) {
		$this->update(
			'DELETE FROM email_templates_settings WHERE locale = ?', $locale
		);
	}

	/**
	 * Delete all default email templates for a specific locale.
	 * @param $locale string
	 */
	function deleteDefaultEmailTemplatesByLocale($locale) {
		$this->update(
			'DELETE FROM email_templates_default_data WHERE locale = ?', $locale
		);
	}

	/**
	 * Check if a template exists with the given email key for a journal/
	 * conference/...
	 * @param $emailKey string
	 * @param $contextId int optional
	 * @return boolean
	 */
	function templateExistsByKey($emailKey, $contextId = null) {
		if ($contextId !== null) {
			$result = $this->retrieve(
				'SELECT	COUNT(*)
				FROM	email_templates
				WHERE	email_key = ? AND
					assoc_type = ? AND
					assoc_id = ?',
				array(
					$emailKey,
					Application::getContextAssocType(),
					(int) $contextId
				)
			);
			if (isset($result->fields[0]) && $result->fields[0] != 0) {
				$result->Close();
				return true;
			}

			$result->Close();
		}

		$result = $this->retrieve(
			'SELECT COUNT(*)
				FROM email_templates_default
				WHERE email_key = ?',
			$emailKey
		);
		if (isset($result->fields[0]) && $result->fields[0] != 0) {
			$returner = true;
		} else {
			$returner = false;
		}

		$result->Close();
		return $returner;
	}

	/**
	 * Check if a custom template exists with the given email key for a
	 * journal/conference/...
	 * @param $emailKey string
	 * @param $contextId int
	 * @return boolean
	 */
	function customTemplateExistsByKey($emailKey, $contextId) {
		$result = $this->retrieve(
			'SELECT	COUNT(*)
			FROM	email_templates e
				LEFT JOIN email_templates_default d ON (e.email_key = d.email_key)
			WHERE	e.email_key = ? AND
				d.email_key IS NULL AND
				e.assoc_type = ? AND
				e.assoc_id = ?',
			array(
				$emailKey,
				Application::getContextAssocType(),
				(int) $contextId
			)
		);
		$returner = (isset($result->fields[0]) && $result->fields[0] != 0);

		$result->Close();
		return $returner;
	}

	/**
	 * Returns an array of custom template keys
	 * @param int $contextId
	 */
	function getCustomTemplateKeys($contextId) {
		$result = $this->retrieve('SELECT email_key FROM email_templates WHERE assoc_type = ? AND assoc_id = ?', array(Application::getContextAssocType(), (int) $contextId));
		$keys = array();
		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$keys[] = $row['email_key'];
			$result->MoveNext();
		}

		return $keys;
	}

	function getMainEmailTemplatesFilename() {
		return 'registry/emailTemplates.xml';
	}

	function getMainEmailTemplateDataFilename($locale = null) {
		if ($locale !== null && !PKPLocale::isLocaleValid($locale)) return null;
		if ($locale === null) $locale = '{$installedLocale}';
		return "locale/$locale/emailTemplates.xml";
	}

	/**
	 * Install email templates from an XML file.
	 * NOTE: Uses qstr instead of ? bindings so that SQL can be fetched
	 * rather than executed.
	 * @param $templatesFile string Filename to install
	 * @param $returnSql boolean Whether or not to return SQL rather than
	 * executing it
	 * @param $emailKey string Optional name of single email key to install,
	 * skipping others
	 * @param $skipExisting boolean If true, do not install email templates
	 * that already exist in the database
	 * @param $emailKey string If specified, the key of the single template
	 * to install (otherwise all are installed)
	 * @return array
	 */
	function installEmailTemplates($templatesFile, $returnSql = false, $emailKey = null, $skipExisting = false) {
		$xmlDao = new XMLDAO();
		$sql = array();
		$data = $xmlDao->parseStruct($templatesFile, array('email'));
		if (!isset($data['email'])) return false;
		foreach ($data['email'] as $entry) {
			$attrs = $entry['attributes'];
			if ($emailKey && $emailKey != $attrs['key']) continue;
			if ($skipExisting && $this->templateExistsByKey($attrs['key'])) continue;
			$dataSource = $this->getDataSource();
			$sql[] = 'DELETE FROM email_templates_default WHERE email_key = ' . $dataSource->qstr($attrs['key']);
			if (!$returnSql) {
				$this->update(array_shift($sql));
			}
			$sql[] = 'INSERT INTO email_templates_default
				(email_key, can_disable, can_edit, from_role_id, to_role_id)
				VALUES
				(' .
				$dataSource->qstr($attrs['key']) . ', ' .
				($attrs['can_disable']?1:0) . ', ' .
				($attrs['can_edit']?1:0) . ', ' .
				(isset($attrs['from_role_id'])?((int) $attrs['from_role_id']):'null') . ', ' .
				(isset($attrs['to_role_id'])?((int) $attrs['to_role_id']):'null') .
				")";
			if (!$returnSql) {
				$this->update(array_shift($sql));
			}
		}
		if ($returnSql) return $sql;
		return true;
	}

	/**
	 * Install email template localized data from an XML file.
	 * NOTE: Uses qstr instead of ? bindings so that SQL can be fetched
	 * rather than executed.
	 * @param $templateDataFile string Filename to install
	 * @param $returnSql boolean Whether or not to return SQL rather than
	 * executing it
	 * @param $emailKey string If specified, the key of the single template
	 * to install (otherwise all are installed)
	 * @return array
	 */
	function installEmailTemplateData($templateDataFile, $returnSql = false, $emailKey = null) {
		$xmlDao = new XMLDAO();
		$sql = array();
		$data = $xmlDao->parse($templateDataFile, array('email_texts', 'email_text', 'subject', 'body', 'description'));
		if (!$data) return false;
		$locale = $data->getAttribute('locale');

		foreach ($data->getChildren() as $emailNode) {
			if ($emailKey && $emailKey != $emailNode->getAttribute('key')) continue;
			$dataSource = $this->getDataSource();
			$sql[] = 'DELETE FROM email_templates_default_data WHERE email_key = ' . $dataSource->qstr($emailNode->getAttribute('key')) . ' AND locale = ' . $dataSource->qstr($locale);
			if (!$returnSql) {
				$this->update(array_shift($sql));
			}

			$sql[] = 'INSERT INTO email_templates_default_data
				(email_key, locale, subject, body, description)
				VALUES
				(' .
				$dataSource->qstr($emailNode->getAttribute('key')) . ', ' .
				$dataSource->qstr($locale) . ', ' .
				$dataSource->qstr($emailNode->getChildValue('subject')) . ', ' .
				$dataSource->qstr($emailNode->getChildValue('body')) . ', ' .
				$dataSource->qstr($emailNode->getChildValue('description')) .
				")";
			if (!$returnSql) {
				$this->update(array_shift($sql));
			}
		}
		if ($returnSql) return $sql;
		return true;
	}
}
