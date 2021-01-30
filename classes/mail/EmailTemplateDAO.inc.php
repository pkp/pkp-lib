<?php

/**
 * @file classes/mail/EmailTemplateDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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

use Illuminate\Database\Capsule\Manager as Capsule;

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
		'stageId' => 'stage_id',
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
		unset($partialObject->_data['stageId']);

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
		unset($partialObject->_data['stageId']);

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
		foreach ($result as $settingRow) {
			$settingRow = (array) $settingRow;
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
		}

		return $emailTemplate;
	}

	/**
	 * Delete all email templates for a specific locale.
	 * @param $locale string
	 */
	function deleteEmailTemplatesByLocale($locale) {
		$this->update(
			'DELETE FROM email_templates_settings WHERE locale = ?', [$locale]
		);
	}

	/**
	 * Delete all default email templates for a specific locale.
	 * @param $locale string
	 */
	function deleteDefaultEmailTemplatesByLocale($locale) {
		$this->update(
			'DELETE FROM email_templates_default_data WHERE locale = ?', [$locale]
		);
	}

	/**
	 * Check if a template exists with the given email key for a journal/
	 * conference/...
	 * @param $key string
	 * @param $contextId int optional
	 * @return boolean
	 */
	function defaultTemplateIsInstalled($key) {
		$result = $this->retrieve(
			'SELECT COUNT(*) AS row_count
				FROM email_templates_default
				WHERE email_key = ?',
			[$key]
		);
		$row = (array) $result->current();
		return $row && $row['row_count'];
	}

	/**
	 * Get the main email template path and filename.
	 * @return string
	 */
	function getMainEmailTemplatesFilename() {
		return 'registry/emailTemplates.xml';
	}

	/**
	 * Install email templates from an XML file.
	 * NOTE: Uses PDO::quote instead of ? bindings so that SQL can be fetched
	 * rather than executed.
	 * @param $templatesFile string Filename to install
	 * @param $locales List of locales to install data for
	 * @param $returnSql boolean Whether or not to return SQL rather than
	 * executing it
	 * @param $emailKey string Optional name of single email key to install,
	 * skipping others
	 * @param $skipExisting boolean If true, do not install email templates
	 * that already exist in the database
	 * @return array|boolean
	 */
	function installEmailTemplates($templatesFile, $locales = array(), $returnSql = false, $emailKey = null, $skipExisting = false) {
		$xmlDao = new XMLDAO();
		$sql = array();
		$data = $xmlDao->parseStruct($templatesFile, array('email'));
		$pdo = Capsule::connection()->getPdo();
		if (!isset($data['email'])) return false;
		foreach ($data['email'] as $entry) {
			$attrs = $entry['attributes'];
			if ($emailKey && $emailKey != $attrs['key']) continue;
			if ($skipExisting && $this->defaultTemplateIsInstalled($attrs['key'])) continue;
			$sql[] = 'DELETE FROM email_templates_default WHERE email_key = ' . $pdo->quote($attrs['key']);
			if (!$returnSql) {
				$this->update(array_shift($sql));
			}
			$sql[] = 'INSERT INTO email_templates_default
				(email_key, can_disable, can_edit, from_role_id, to_role_id, stage_id)
				VALUES
				(' .
				$pdo->quote($attrs['key']) . ', ' .
				($attrs['can_disable']?1:0) . ', ' .
				($attrs['can_edit']?1:0) . ', ' .
				(isset($attrs['from_role_id'])?((int) $attrs['from_role_id']):'null') . ', ' .
				(isset($attrs['to_role_id'])?((int) $attrs['to_role_id']):'null') . ', ' .
				(isset($attrs['stage_id'])?((int) $attrs['stage_id']):'null') .
				")";
			if (!$returnSql) {
				$this->update(array_shift($sql));
			}

			// Add localized data
			$additionalQueries = $this->installEmailTemplateLocaleData($templatesFile, $locales, $returnSql, $attrs['key']);
			if ($returnSql) $sql = array_merge($sql, $additionalQueries);
		}
		if ($returnSql) return $sql;
		return true;
	}

	/**
	 * Install email template contents from an XML file.
	 * NOTE: Uses PDO::quote instead of ? bindings so that SQL can be fetched
	 * rather than executed.
	 * @param $templatesFile string Filename to install
	 * @param $locales List of locales to install data for
	 * @param $returnSql boolean Whether or not to return SQL rather than
	 * executing it
	 * @param $emailKey string Optional name of single email key to install,
	 * skipping others
	 * @return array|boolean
	 */
	function installEmailTemplateLocaleData($templatesFile, $locales = array(), $returnSql = false, $emailKey = null) {
		$xmlDao = new XMLDAO();
		$sql = array();
		$data = $xmlDao->parseStruct($templatesFile, array('email'));
		if (!isset($data['email'])) return false;

		$pdo = Capsule::connection()->getPdo();
		foreach ($data['email'] as $entry) {
			$attrs = $entry['attributes'];
			if ($emailKey && $emailKey != $attrs['key']) continue;

			$subject = $attrs['subject']??null;
			$body = $attrs['body']??null;
			$description = $attrs['description']??null;
			if ($subject && $body) foreach ($locales as $locale) {
				$sql[] = 'DELETE FROM email_templates_default_data WHERE email_key = ' . $pdo->quote($attrs['key']) . ' AND locale = ' . $pdo->quote($locale);
				if (!$returnSql) {
					$this->update(array_shift($sql));
				}

				$keyNotFoundHandler = function($key) {
					return null;
				};
				$translatedSubject = __($subject, [], $locale, $keyNotFoundHandler);
				$translatedBody = __($body, [], $locale, $keyNotFoundHandler);
				if ($translatedSubject !== null && $translatedBody !==  null) {
					$sql[] = 'INSERT INTO email_templates_default_data
						(email_key, locale, subject, body, description)
						VALUES
						(' .
						$pdo->quote($attrs['key']) . ', ' .
						$pdo->quote($locale) . ', ' .
						$pdo->quote($translatedSubject) . ', ' .
						$pdo->quote($translatedBody) . ', ' .
						$pdo->quote(__($description, [], $locale)) .
						")";
					if (!$returnSql) {
						$this->update(array_shift($sql));
					}
				}
			}
		}
		if ($returnSql) return $sql;
		return true;
	}

	/**
	 * Install email template localized data from an XML file.
	 * NOTE: Uses PDO::quote instead of ? bindings so that SQL can be fetched
	 * rather than executed.
	 * @deprecated Since OJS/OMP 3.2, this data should be supplied via the non-localized email template list and PO files. (pkp/pkp-lib#5461)
	 * @param $templateDataFile string Filename to install
	 * @param $locale string Locale of template(s) to install
	 * @param $returnSql boolean Whether or not to return SQL rather than
	 * executing it
	 * @param $emailKey string If specified, the key of the single template
	 * to install (otherwise all are installed)
	 * @return array|boolean
	 */
	function installEmailTemplateData($templateDataFile, $locale, $returnSql = false, $emailKey = null) {
		$xmlDao = new XMLDAO();
		$sql = array();
		$data = $xmlDao->parse($templateDataFile, array('email_texts', 'email_text', 'subject', 'body', 'description'));
		if (!$data) return false;

		$pdo = Capsule::connection()->getPdo();
		foreach ($data->getChildren() as $emailNode) {
			$subject = $emailNode->getChildValue('subject');
			$body = $emailNode->getChildValue('body');
			$description = $emailNode->getChildValue('description');

			// Translate variable contents
			foreach (array(&$subject, &$body, &$description) as &$var) {
				$var = preg_replace_callback('{{translate key="([^"]+)"}}', function($matches) {
					return __($matches[1], array(), $locale);
				}, $var);
			}

			if ($emailKey && $emailKey != $emailNode->getAttribute('key')) continue;
			$sql[] = 'DELETE FROM email_templates_default_data WHERE email_key = ' . $pdo->quote($emailNode->getAttribute('key')) . ' AND locale = ' . $pdo->quote($locale);
			if (!$returnSql) {
				$this->update(array_shift($sql));
			}

			$sql[] = 'INSERT INTO email_templates_default_data
				(email_key, locale, subject, body, description)
				VALUES
				(' .
				$pdo->quote($emailNode->getAttribute('key')) . ', ' .
				$pdo->quote($locale) . ', ' .
				$pdo->quote($subject) . ', ' .
				$pdo->quote($body) . ', ' .
				$pdo->quote($description) .
				")";
			if (!$returnSql) {
				$this->update(array_shift($sql));
			}
		}
		if ($returnSql) return $sql;
		return true;
	}
}
