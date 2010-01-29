<?php

/**
 * @file classes/metadata/NlmCitationSchemaCitationAdapter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NlmCitationSchemaCitationAdapter
 * @ingroup metadata_nlm
 * @see Citation
 * @see NlmCitationSchema
 *
 * @brief Class that injects/extracts NLM citation schema compliant
 *  meta-data into/from a Citation object.
 */

// $Id$

import('citation.MetadataCitationAdapter');
import('metadata.NlmCitationSchema');

class NlmCitationSchemaCitationAdapter extends MetadataCitationAdapter {
	/**
	 * Constructor
	 */
	function NlmCitationSchemaCitationAdapter() {
		// Configure the adapter
		$metadataSchema = new NlmCitationSchema();
		parent::MetadataCitationAdapter($metadataSchema);
	}

	//
	// Implement template methods from MetadataCitationAdapter
	//
	/**
	 * Get authors as a string representation
	 * @param $citationDescription MetadataDescription
	 * @return string authors in the format "Bohr, Niels; van der Waals, J. D.; Planck, Max"
	 */
	function getAuthorsString(&$citation) {
		$authors = $citationDescription->getData($this->getMetadataNamespace().':person-group[@person-group-type="author"]');
		if (!is_array($authors)) return null;

		$authorsString = '';
		foreach ($authors as $author) {
			assert(is_a($author, 'MetadataDescription'));
			$authorsString .= $author->getLastName().', '.$author->getFirstName().' '.$author->getMiddleName().';';
		}
		// Remove the final semicolon
		return substr($authorsString, 0, -1);
	}

	//
	// Implement template methods from MetadataDataObjectAdapter
	//
	/**
	 * @see MetadataDataObjectAdapter::injectMetadataIntoDataObject()
	 * @param $metadataDescription MetadataDescription
	 * @param $dataObject Citation
	 * @return DataObject
	 */
	function &injectMetadataIntoDataObject(&$metadataDescription, &$dataObject) {
		// Did we get an existing citation object or should we create a new one?
		if (is_null($dataObject)) {
			import('citation.Citation');
			$dataObject = new Citation();
		}

		// Retrieve the new statements
		$statements =& $metadataDescription->getStatements();

		// Add new meta-data statements to the citation. Add the schema
		// name space to each property name so that it becomes unique
		// across schemas.
		$metadataSchemaNamespace = $this->getMetadataNamespace();

		foreach($statements as $propertyName => $value) {
			if (in_array($propertyName, array('person-group[@person-group-type="author"]', 'person-group[@person-group-type="editor"]'))) {
				// Convert MetadataDescription objects to simple key/value arrays.
				assert(is_array($value));
				foreach($value as $key => $nameComposite) {
					assert(is_a($nameComposite, 'MetadataDescription'));
					$value[$key] =& $nameComposite->getAllData();
				}
			}
			$dataObject->setData($metadataSchemaNamespace.':'.$propertyName, $value);
		}

		return $dataObject;
	}

	/**
	 * @see MetadataDataObjectAdapter::extractMetadataFromDataObject()
	 * @param $dataObject Citation
	 * @return MetadataDescription
	 */
	function &extractMetadataFromDataObject(&$dataObject) {
		$metadataDescription =& $this->instantiateMetadataDescription();

		// Identify the length of the name space prefix
		$namespacePrefixLength = strlen($this->getMetadataNamespace())+1;

		// Get all meta-data field names
		$fieldNames = array_merge($this->getDataObjectMetadataFieldNames(false),
				$this->getDataObjectMetadataFieldNames(true));

		// Retrieve the statements from the data object
		$statements = array();
		$nameSchema = new NlmNameSchema();
		foreach($fieldNames as $fieldName) {
			if ($dataObject->hasData($fieldName)) {
				// Remove the name space prefix
				$propertyName = substr($fieldName, $namespacePrefixLength);
				if (in_array($propertyName, array('person-group[@person-group-type="author"]', 'person-group[@person-group-type="editor"]'))) {
					// Convert key/value arrays to MetadataDescription objects.
					$names =& $dataObject->getData($fieldName);
					foreach($names as $key => $name) {
						switch($propertyName) {
							case 'person-group[@person-group-type="author"]':
								$assocType = ASSOC_TYPE_AUTHOR;
								break;

							case 'person-group[@person-group-type="editor"]':
								$assocType = ASSOC_TYPE_EDITOR;
								break;
						}
						$nameDescription = new MetadataDescription($nameSchema, $assocType);
						$nameDescription->setStatements($name);
						$names[$key] =& $nameDescription;
					}
					$statements[$propertyName] =& $names;
				} else {
					$statements[$propertyName] =& $dataObject->getData($fieldName);
				}
			}
		}

		// Set the statements in the meta-data description
		$metadataDescription->setStatements($statements);

		return $metadataDescription;
	}
}
?>