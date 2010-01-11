<?php

/**
 * @file MetadataDescriptionDAO.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataDescriptionDAO
 * @ingroup metadata
 * @see MetadataDescription
 *
 * @brief Operations for retrieving and modifying MetadataDescription objects
 */

//$Id$

import('metadata.MetadataDescription');

class MetadataDescriptionDAO extends DAO {
	/** @var array maps meta-data schema names to meta-data schemas classes. */
	var $_metadataSchemaClasses = array(
		// Currently we only have one schema that's persisted to the database
		// TODO: Replace this with a db table once we want to make the schemas configurable.
		'nlm-3.0-element-citation' => 'metadata.NlmCitationSchema'
	);

	/** @var MetadataDescription */
	var $_metadataDescription;

	/** @var array */
	var $_localeFieldNames;

	/** @var array */
	var $_additionalFieldNames;

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @param $metadataSchemaName string
	 * @param $assocType int
	 * @return MetadataDescription
	 */
	function newDataObject($metadataSchemaName, $assocType) {
		$metadataSchema =& $this->instantiateMetadataSchema($metadataSchemaName, $assocType);
		if (is_null($metadataSchema)) fatalError('An unknown meta-data schema has been found in the database!');
		$this->_metadataDescription =& new MetadataDescription($metadataSchema);
		$this->_localeFieldNames = array();
		$this->_additionalFieldNames = array();
		$this->filterFieldNames();
	}

	/**
	 * Retrieve the field names (properties) available for the
	 * current assoc type with (or without) internationalization.
	 * @param $translated boolean
	 * @return array an array of field names
	 */
	function filterFieldNames() {
		assert(isset($this->_metadataDescription));
		assert($this->_localeFieldNames == array() && $this->_additionalFieldNames == array());

		$metadataDescription =& $this->_metadataDescription;
		$assocType = $metadataDescription->getAssocType();
		$properties =& $metadataDescription->getProperties();

		$fieldNames = array();
		foreach($properties as $property) {
			$propertyAssocTypes = $property->getAssocTypes();
			if (in_array($assocType, $propertyAssocTypes)) {
				if ($property->getTranslated()) {
					$this->_localeFieldNames[] = $property->getName();
				} else {
					$this->_additionalFieldNames[] = $property->getName();
				}
			}
		}
	}

	/**
	 * Instantiates a meta-data schema based on its name.
	 * Returns null when an unknown meta-data name is passed in.
	 * @param $metadataSchemaName string
	 * @param $assocType int
	 * @return MetadataSchema
	 */
	function &instantiateMetadataSchema($metadataSchemaName, $assocType) {
		if(isset($this->_metadataSchemaClasses[$metadataSchemaName])) {
			$qualifiedMetadataSchemaClass = $this->_metadataSchemaClasses[$metadataSchemaName];
			import($qualifiedMetadataSchemaClass);
			$qualifiedMetaDataSchemaClassParts = explode($qualifiedMetadataSchemaClass);
			$metaDataSchemaClass = array_pop($qualifiedMetaDataSchemaClassParts);
			$metaDataSchema =& new $metaDataSchemaClass();
		} else {
			$metaDataSchema = null;
		}
		return $metaDataSchema;
	}

	/**
	 * Internal function to return an MetadataDescription object from a
	 * row.
	 * @param $row array
	 * @return MetadataDescription
	 */
	function _fromRow(&$row) {
		$this->newDataObject($row['metadata_schema'], $row['assoc_type']);
		$this->_metadataDescription->setId($row['metadata_description_id']);

		$this->getDataObjectSettings('metadata_description_settings', 'metadata_description_id', $row['metadata_description_id'], $metadataDescription);

		return $this->_metadataDescription;
	}

	/* (non-PHPdoc)
	 * @see lib/pkp/classes/db/DAO#getLocaleFieldNames()
	 */
	function getLocaleFieldNames() {
		return $this->_localeFieldNames;
	}

	/* (non-PHPdoc)
	 * @see lib/pkp/classes/db/DAO#getAdditionalFieldNames()
	 */
	function getAdditionalFieldNames() {
		return $this->_additionalFieldNames;
	}

	/**
	 * Update the localized fields for this table
	 * @param $metadataDescription MetadataDescription
	 */
	function updateStatements(&$metadataDescription) {
		$this->updateDataObjectSettings('metadata_description_settings', $metadataDescription,
				array('metadata_description_id' => $metadataDescription->getId()));
	}

	/**
	 * Insert a new MetadataDescription.
	 * @param $metadataDescription MetadataDescription
	 * @return int the new id
	 */
	function insertObject(&$metadataDescription) {
		$metadataSchema =& $metadataDescription->getMetadataSchema();
		$this->update(
			sprintf('INSERT INTO metadata_descriptions
				(metadata_description_id, assoc_type, assoc_id, metadata_schema)
				VALUES
				(?, ?, ?, ?)'),
			array(
				(int)$metadataDescription->getId(),
				(int)$metadataDescription->getAssocType(),
				(int)$metadataDescription->getAssocId(),
				(string)$metadataSchema->getName()
			)
		);
		$metadataDescription->setId($this->getInsertId());
		$this->updateStatements($metadataDescription);
		return $metadataDescription->getId();
	}

	/**
	 * Delete a MetadataDescription.
	 * @param $metadataDescription MetadataDescription
	 * @return boolean
	 */
	function deleteObject($metadataDescription) {
		return $this->deleteObjectById($metadataDescription->getId());
	}

	/**
	 * Delete a MetadataDescription by ID.
	 * @param $metadataDescriptionId int
	 * @return boolean
	 */
	function deleteObjectById($metadataDescriptionId) {
		$params = array((int) $metadataDescriptionId);
		$this->update('DELETE FROM metadata_description_settings WHERE metadata_description_id = ?', $params);
		return $this->update('DELETE FROM metadata_descriptions WHERE metadata_description_id = ?', $params);
	}

	/**
	 * Retrieve an iterator of MetadataDescriptions matching a
	 * particular ID.
	 * @param $metadataDescriptionId int
	 * @return object DAOResultFactory containing matching MetadataDescription objects
	 */
	function getById($metadataDescriptionId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT * FROM metadata_descriptions WHERE metadata_description_id = ?',
			array((int) $metadataDescriptionId),
			$rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_fromRow');
		return $returner;
	}

	/**
	 * Update an existing MetadataDescription.
	 * @param $metadataDescription MetadataDescription
	 */
	function updateObject(&$metadataDescription) {
		$metadataSchema =& $metadataDescription->getMetadataSchema();
		$returner = $this->update(
			'UPDATE	metadata_descriptions
			SET	metadata_description_id = ?,
				assoc_type = ?,
				assoc_id = ?,
				metadata_schema = ?
			WHERE	metadata_description_id = ?',
			array(
				(int)$metadataDescription->getId(),
				(int)$metadataDescription->getAssocType(),
				(int)$metadataDescription->getAssocId(),
				(string)$metadataSchema->getName()
			)
		);
		$this->updateStatements($metadataDescription);
	}

	/**
	 * Get the ID of the last inserted MetadataDescription.
	 * @return int
	 */
	function getInsertId() {
		return parent::getInsertId('metadata_descriptions', 'metadata_description_id');
	}
}

?>
