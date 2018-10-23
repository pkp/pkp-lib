<?php
/**
 * @file classes/db/SchemaDAO.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SchemaDAO
 * @ingroup db
 *
 * @brief A base class for DAOs which rely on a json-schema file to define
 *  the data object.
 */
import('lib.pkp.classes.db.DAO');
import('classes.core.ServicesContainer');

abstract class SchemaDAO extends DAO {
	/** @var string One of the SCHEMA_... constants */
	var $schemaName;

	/** @var string The name of the primary table for this object */
	var $tableName;

	/** @var string The name of the settings table for this object */
	var $settingsTableName;

	/** @var string The column name for the object id in primary and settings tables */
	var $primaryKeyColumn;

	/** @var array Maps schema properties for the primary table to their column names */
	var $primaryTableColumns = array();

	/**
	 * Create a new DataObject of the appropriate class
	 *
	 * @return DataObject
	 */
	abstract public function newDataObject();

	/**
	 * Insert a new object
	 *
	 * @param $object DataObject The object to insert into the database
	 * @return int The new object's id
	 */
	public function insertObject($object) {
		$schemaService = ServicesContainer::instance()->get('schema');
		$schema = $schemaService->get($this->schemaName);
		$sanitizedProps = $schemaService->sanitize($this->schemaName, $object->_data);

		$primaryDbProps = [];
		foreach ($this->primaryTableColumns as $propName => $columnName) {
			if (isset($sanitizedProps[$propName])) {
				$primaryDbProps[$columnName] = $sanitizedProps[$propName];
			}
		}

		if (empty($primaryDbProps)) {
			fatalError('Tried to insert ' . get_class($object) . ' without any properties for the ' . $this->tableName . ' table.');
		}

		$columnsList = join(', ', array_keys($primaryDbProps));
		$bindList = join(', ', array_fill(0, count($primaryDbProps), '?'));
		$this->update("INSERT INTO $this->tableName ($columnsList) VALUES ($bindList)", array_values($primaryDbProps));

		$object->setId($this->getInsertId());

		// Add additional properties to settings table if they exist
		if (count($sanitizedProps) !== count($primaryDbProps)) {
			$columns = array($this->primaryKeyColumn, 'locale', 'setting_name', 'setting_value');
			$columnsList = join(', ', $columns);
			$bindList = join(', ', array_fill(0, count($columns), '?'));
			foreach ($schema->properties as $propName => $propSchema) {
				if (!isset($sanitizedProps[$propName]) || array_key_exists($propName, $this->primaryTableColumns)) {
					continue;
				}
				if (!empty($propSchema->multilingual)) {
					foreach ($sanitizedProps[$propName] as $localeKey => $localeValue) {
						$this->update("INSERT INTO $this->settingsTableName ($columnsList) VALUES ($bindList)", array(
							$object->getId(),
							$localeKey,
							$propName,
							$this->convertToDB($localeValue, $schema->properties->{$propName}->type),
						));
					}
				} else {
					$this->update("INSERT INTO $this->settingsTableName ($columnsList) VALUES ($bindList)", array(
						$object->getId(),
						'',
						$propName,
						$this->convertToDB($sanitizedProps[$propName], $schema->properties->{$propName}->type),
					));
				}
			}
		}

		return $object->getId();
	}

	/**
	 * Update an object
	 *
	 * When updating an object, we remove table rows for any settings which are
	 * no longer present.
	 *
	 * Multilingual fields are an exception to this. When a locale key is missing
	 * from the object, it is not removed from the database. This is to ensure
	 * that data from a locale which is later disabled is not automatically
	 * removed from the database, and will appear if the locale is re-enabled.
	 *
	 * To delete a value for a locale key, a null value must be passed.
	 *
	 * @param $object DataObject The object to insert into the database
	 */
	public function updateObject($object) {
		$schemaService = ServicesContainer::instance()->get('schema');
		$schema = $schemaService->get($this->schemaName);
		$sanitizedProps = $schemaService->sanitize($this->schemaName, $object->_data);

		$primaryDbProps = [];
		foreach ($this->primaryTableColumns as $propName => $columnName) {
			if ($propName !== 'id' && isset($sanitizedProps[$propName])) {
				$primaryDbProps[$columnName] = $this->convertToDB($sanitizedProps[$propName], $schema->properties->{$propName}->type);
			}
		}

		$set = join('=?,', array_keys($primaryDbProps)) . '=?';
		$this->update(
			"UPDATE $this->tableName SET $set WHERE $this->primaryKeyColumn = ?",
			array_merge(array_values($primaryDbProps), array($object->getId()))
		);

		$deleteSettings = [];
		$keyColumns = [$this->primaryKeyColumn, 'locale', 'setting_name'];
		foreach ($schema->properties as $propName => $propSchema) {
			if (array_key_exists($propName, $this->primaryTableColumns)) {
				continue;
			} elseif (!isset($sanitizedProps[$propName])) {
				$deleteSettings[] = $propName;
				continue;
			}
			if (!empty($propSchema->multilingual)) {
				foreach ($sanitizedProps[$propName] as $localeKey => $localeValue) {
					// Delete rows with a null value
					if (is_null($localeValue)) {
						$this->update("DELETE FROM $this->settingsTableName WHERE setting_name = ? AND locale = ?",[
							$propName,
							$localeKey,
						]);
					} else {
						$updateArray = [
							$this->primaryKeyColumn => $object->getId(),
							'locale' => $localeKey,
							'setting_name' => $propName,
							'setting_value' => $this->convertToDB($localeValue, $schema->properties->{$propName}->type),
						];
						$this->replace($this->settingsTableName, $updateArray, $keyColumns);
					}
				}
			} else {
				$updateArray = [
					$this->primaryKeyColumn => $object->getId(),
					'locale' => '',
					'setting_name' => $propName,
					'setting_value' => $this->convertToDB($sanitizedProps[$propName], $schema->properties->{$propName}->type),
				];
				$this->replace($this->settingsTableName, $updateArray, $keyColumns);
			}
		}

		if (count($deleteSettings)) {
			$deleteSettingNames = join(',', array_map(function($settingName) {
				return "'$settingName'";
			}, $deleteSettings));
			$this->update("DELETE FROM $this->settingsTableName WHERE setting_name in ($deleteSettingNames)");
		}
	}

	/**
	 * Delete an object
	 *
	 * A wrapper function for SchemaDAO::deleteObjectById().
	 *
	 * @param $object DataObject The object to insert into the database
	 */
	public function deleteObject($object) {
		$this->deleteById($object->getId());
	}

	/**
	 * Delete an object by its ID
	 *
	 * @param $objectId int
	 */
	public function deleteById($objectId) {
		$this->update(
			"DELETE FROM $this->tableName WHERE $this->primaryKeyColumn = ?",
			(int) $objectId
		);
		$this->update(
			"DELETE FROM $this->settingsTableName WHERE $this->primaryKeyColumn = ?",
			(int) $objectId
		);
	}

	/**
	 * Return a DataObject from a result row
	 *
	 * @param $primaryRow array The result row from the primary table lookup
	 * @return DataObject
	 */
	public function _fromRow($primaryRow) {
		$schemaService = ServicesContainer::instance()->get('schema');
		$schema = $schemaService->get($this->schemaName);

		$object = $this->newDataObject();

		foreach ($this->primaryTableColumns as $propName => $column) {
			if (isset($primaryRow[$column])) {
				$object->setData(
					$propName,
					$this->convertFromDb($primaryRow[$column], $schema->properties->{$propName}->type)
				);
			}
		}

		$result = $this->retrieve(
			"SELECT * FROM $this->settingsTableName WHERE $this->primaryKeyColumn = ?",
			array($primaryRow[$this->primaryKeyColumn])
		);

		while (!$result->EOF) {
			$settingRow = $result->getRowAssoc(false);
			if (!empty($schema->properties->{$settingRow['setting_name']})) {
				$object->setData(
					$settingRow['setting_name'],
					$this->convertFromDB(
						$settingRow['setting_value'],
						$schema->properties->{$settingRow['setting_name']}->type
					),
					empty($settingRow['locale']) ? null : $settingRow['locale']
				);
			}
			$result->MoveNext();
		}

		return $object;
	}
}
