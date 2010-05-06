<?php

/**
 * @file controllers/grid/citation/form/CitationForm.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationForm
 * @ingroup controllers_grid_citation_form
 *
 * @brief Form for adding/editing a citation
 * stores/retrieves from an associative array
 */

import('lib.pkp.classes.form.Form');

class CitationForm extends Form {
	/** @var Citation the citation being edited */
	var $_citation;

	/** @var boolean */
	var $_unsavedChanges;

	/** @var array all properties contained in the citation */
	var $_citationProperties;

	/**
	 * Constructor.
	 * @param $citation Citation
	 * @param $unsavedChanges boolean should be set to true if the
	 *  data displayed in the form has not yet been persisted.
	 */
	function CitationForm($citation, $unsavedChanges = false) {
		parent::Form('controllers/grid/citation/form/citationForm.tpl');

		assert(is_a($citation, 'Citation'));
		$this->_citation =& $citation;

		$this->_unsavedChanges = (boolean) $unsavedChanges;

		// Identify all form field names for the citation
		$this->_citationFormFieldNames = array();
		foreach($citation->getSupportedMetadataAdapters() as $metadataAdapter) {
			// Retrieve the meta-data schema
			$metadataSchema =& $metadataAdapter->getMetadataSchema();

			// Loop over the properties names in the schema and save
			// them in a flat list.
			$properties = $metadataSchema->getProperties();
			foreach($properties as $property) {
				$this->_citationProperties[$metadataSchema->getNamespacedPropertyId($property->getName())] =& $property;
				unset($property);
			}
		}

		// Validation checks for this form that are not checked within the default meta-data validation algorithm.
		$this->addCheck(new FormValidator($this, 'editedCitation', 'required', 'submission.citations.grid.editedCitationRequired'));
		$this->addCheck(new FormValidatorPost($this));

		// FIXME: Validate citation state
	}

	//
	// Getters and Setters
	//
	/**
	* Get the citation
	* @return Citation
	*/
	function &getCitation() {
		return $this->_citation;
	}

	/**
	 * Returns true if the form contains unsaved changes,
	 * otherwise false.
	 * @return boolean
	 */
	function getUnsavedChanges() {
		return $this->_unsavedChanges;
	}

	//
	// Template methods from Form
	//
	/**
	* Initialize form data from the associated citation.
	*/
	function initData() {
		$citation =& $this->getCitation();

		// The unparsed citation text and the citation state
		$this->setData('editedCitation', $citation->getEditedCitation());

		// Citation meta-data
		foreach($citation->getSupportedMetadataAdapters() as $metadataAdapter) {
			// Retrieve the meta-data schema
			$metadataSchema =& $metadataAdapter->getMetadataSchema();

			// Loop over the properties in the schema and add string
			// values for all form fields.
			$citationVars = array();
			$citationVarsEmpty = array();
			$properties = $metadataSchema->getProperties();
			$metadataDescription =& $citation->extractMetadata($metadataSchema);
			foreach($properties as $propertyName => $property) {
				if ($metadataDescription->hasStatement($propertyName)) {
					$value = $metadataDescription->getStatement($propertyName);

					if ($property->getCardinality() == METADATA_PROPERTY_CARDINALITY_MANY && !empty($value)) {
						$allowedTypes = $property->getAllowedTypes();
						if (isset($allowedTypes[METADATA_PROPERTY_TYPE_COMPOSITE])) {
							// We currently only support composite name arrays
							$allowedAssocTypes = $allowedTypes[METADATA_PROPERTY_TYPE_COMPOSITE];
							assert(in_array(ASSOC_TYPE_AUTHOR, $allowedAssocTypes) || in_array(ASSOC_TYPE_EDITOR, $allowedAssocTypes));
							import('lib.pkp.classes.metadata.nlm.NlmNameSchemaPersonStringFilter');
							$personStringFilter = new NlmNameSchemaPersonStringFilter(PERSON_STRING_FILTER_MULTIPLE);
							assert($personStringFilter->supportsAsInput($value));
							$fieldValue = $personStringFilter->execute($value);
						} else {
							// We currently don't support properties of
							// cardinality "many" in the form.
							assert(is_array($value) && count($value) <= 1);
							$fieldValue = $value[0];
						}
					} else {
						$fieldValue = (string)$value;
					}
				} else {
					$fieldValue = '';
				}
				$fieldName = $metadataSchema->getNamespacedPropertyId($propertyName);
				$this->setData($fieldName, $fieldValue);
			}
		}
	}

	/**
	 * Initialize form data from user submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('editedCitation', 'citationState'));
		$this->readUserVars(array_keys($this->_citationProperties));
	}

	/**
	 * Fetch the form.
	 * @param $request Request
	 * @return the rendered form
	 */
	function fetch($request) {
		// Template
		$templateMgr =& TemplateManager::getManager($request);

		// Auto-add client-side validation
		$templateMgr->assign('validateId', 'citationForm');

		// Form tabs
		// FIXME: At the moment, we just create two tabs -- one for filled
		// elements, and one for empty ones. Any number of elements can be
		// added, and they will appear as new tabs on the modal window.
		$citationFormTabs = array('Filled Elements' => array(), 'Empty Elements' => array());
		foreach($this->_citationProperties as $fieldName => $property) {
			$tabName = ($this->getData($fieldName) == '' ? 'Empty Elements' : 'Filled Elements');
			$citationFormTabs[$tabName][$fieldName] = $property->getDisplayName();
		}
		$templateMgr->assign_by_ref('citationFormTabs', $citationFormTabs);
		$templateMgr->assign('tabUid', time());

		// Add the citation to the template
		$citation =& $this->getCitation();
		$templateMgr->assign_by_ref('citation', $citation);

		// Does the form contain unsaved changes?
		$templateMgr->assign('unsavedChanges', $this->getUnsavedChanges());

		// Add actions for parsing and lookup
		$router = $request->getRouter();
		$checkAction = new GridAction(
			'checkCitation',
			GRID_ACTION_MODE_AJAX,
			GRID_ACTION_TYPE_POST,
			$router->url($request, null, null, 'checkCitation'),
			'submission.citations.grid.checkCitationAgain'
		);
		$templateMgr->assign_by_ref('checkAction', $checkAction);

		return $this->display($request, true);
	}

	/**
	 * Custom implementation of Form::validate() that validates
	 * meta-data form data.
	 */
	function validate() {
		parent::validate();

		// Validate form data and inject it into
		// the associated citation object.
		$citation =& $this->getCitation();
		$citation->setEditedCitation($this->getData('editedCitation'));
		if (in_array($this->getData('citationState'), Citation::_getSupportedCitationStates())) {
			$citation->setCitationState($this->getData('citationState'));
		}

		// Extract data from citation form fields and inject it into the citation
		import('lib.pkp.classes.metadata.MetadataDescription');
		$metadataAdapters = $citation->getSupportedMetadataAdapters();
		foreach($metadataAdapters as $metadataAdapter) {
			// Instantiate a meta-data description for the given schema
			$metadataSchema =& $metadataAdapter->getMetadataSchema();
			$metadataDescription = new MetadataDescription($metadataSchema, ASSOC_TYPE_CITATION);

			// Set the meta-data statements
			foreach($metadataSchema->getProperties() as $propertyName => $property) {
				$fieldName = $metadataSchema->getNamespacedPropertyId($propertyName);
				$fieldValue = trim($this->getData($fieldName));
				if (empty($fieldValue)) {
					// Delete empty statements so that previously set
					// statements (if any) will be deleted.
					$metadataDescription->removeStatement($propertyName);

					if ($property->getMandatory()) {
						// A mandatory field is missing - add a validation error.
						$this->addError($fieldName, Locale::translate($property->getValidationMessage()));
						$this->addErrorField($fieldName);
					}
				} else {
					// Try to convert the field value to (a) strongly
					// typed object(s) if applicable. Start with the most
					// specific allowed type so that we always get the
					// most strongly typed result possible.
					$allowedTypes = $property->getAllowedTypes();
					switch(true) {
						case isset($allowedTypes[METADATA_PROPERTY_TYPE_VOCABULARY]) && is_numeric($fieldValue):
						case isset($allowedTypes[METADATA_PROPERTY_TYPE_INTEGER]) && is_numeric($fieldValue):
							$typedFieldValues = array((integer)$fieldValue);
							break;

						case isset($allowedTypes[METADATA_PROPERTY_TYPE_DATE]):
							import('lib.pkp.classes.metadata.DateStringNormalizerFilter');
							$dateStringFilter = new DateStringNormalizerFilter();
							assert($dateStringFilter->supportsAsInput($fieldValue));
							$typedFieldValues = array($dateStringFilter->execute($fieldValue));
							break;

						case isset($allowedTypes[METADATA_PROPERTY_TYPE_COMPOSITE]):
							// We currently only support name composites
							$allowedAssocIds = $allowedTypes[METADATA_PROPERTY_TYPE_COMPOSITE];
							if(in_array(ASSOC_TYPE_AUTHOR, $allowedAssocIds)) {
								$assocType = ASSOC_TYPE_AUTHOR;
							} elseif (in_array(ASSOC_TYPE_EDITOR, $allowedAssocIds)) {
								$assocType = ASSOC_TYPE_EDITOR;
							} else {
								assert(false);
							}

							// Try to transform the field to a name composite.
							import('lib.pkp.classes.metadata.nlm.PersonStringNlmNameSchemaFilter');
							$personStringFilter = new PersonStringNlmNameSchemaFilter($assocType, PERSON_STRING_FILTER_MULTIPLE);
							assert($personStringFilter->supportsAsInput($fieldValue));
							$typedFieldValues =& $personStringFilter->execute($fieldValue);
							break;

						default:
							$typedFieldValues = array($fieldValue);
					}

					// Inject data into the meta-data description and thereby
					// implicitly validate the field value.
					foreach($typedFieldValues as $typedFieldValue) {
						if(!$metadataDescription->addStatement($propertyName, $typedFieldValue)) {
							// Add form field error
							$this->addError($fieldName, Locale::translate($property->getValidationMessage()));
							$this->addErrorField($fieldName);
						}
						unset($typedFieldValue);
					}
					unset($typedFieldValues);
				}
			}

			// Inject the meta-data into the citation
			$citation->injectMetadata($metadataDescription, true);
			unset($metadataDescription);
		}

		return $this->isValid();
	}

	/**
	 * Save citation
	 */
	function execute() {
		// Persist citation
		$citation =& $this->getCitation();
		$citationDAO =& DAORegistry::getDAO('CitationDAO');
		if (is_numeric($citation->getId())) {
			$citationDAO->updateCitation($citation);
		} else {
			$citationDAO->insertCitation($citation);
		}
		return true;
	}
}

?>
