<?php

/**
 * @file controllers/grid/citation/form/CitationForm.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationForm
 * @ingroup controllers_grid_citation_form
 *
 * @brief Form for adding/editing a citation
 * stores/retrieves from an associative array
 */

import('form.Form');

class CitationForm extends Form {
	/** Citation the citation being edited */
	var $_citation;

	/** boolean */
	var $_unsavedChanges;

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

		// Validation checks for this form
		$this->addCheck(new FormValidator($this, 'editedCitation', 'required', 'submission.citations.grid.editedCitationRequired'));
		$this->addCheck(new FormValidator($this, 'nlm30PublicationType', 'required', 'submission.citations.grid.publicationTypeRequired'));
		$this->addCheck(new FormValidatorPost($this));

		// FIXME: Write and add meta-data description validator
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
	* @param $citation Citation
	*/
	function initData() {
		$citation =& $this->getCitation();

		// The unparsed citation text and the citation state
		$this->setData('editedCitation', $citation->getEditedCitation());

		// Citation meta-data
		foreach($citation->getSupportedMetadataAdapters() as $metadataAdapter) {
			// Retrieve the meta-data schema
			$metadataSchema =& $metadataAdapter->getMetadataSchema();
			$metadataSchemaNamespace = $metadataSchema->getNamespace();

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
						// FIXME: The following is a work-around until we completely
						// implement #5171 ("author: et.al"). Then we have to support
						// true multi-type properties.
						if (is_a($value[0], 'MetadataDescription')) {
							// We currently only support composite name arrays
							assert(in_array($value[0]->getAssocType(), array(ASSOC_TYPE_AUTHOR, ASSOC_TYPE_EDITOR)));
							import('metadata.nlm.NlmNameSchemaPersonStringFilter');
							$personStringFilter = new NlmNameSchemaPersonStringFilter(PERSON_STRING_FILTER_MULTIPLE);
							assert($personStringFilter->supportsAsInput($value));
							$fieldValue = $personStringFilter->execute($value);
						} else {
							// We currently don't support repeated values
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
				if ($fieldValue != '') {
					$citationVars[$fieldName] = array(
						'translationKey' => $property->getDisplayName(),
						'value' => $fieldValue
					);
				} else {
					$citationVarsEmpty[$fieldName] = array(
						'translationKey' => $property->getDisplayName(),
						'value' => $fieldValue
					);
				}
			}
		}
		// FIXME: at the moment, we just create two tabs -- one for filled elements, and one for empty ones.
		// any number of elements can be added, and they will appear as new tabs on the modal window.
		$citationVarArrays = array("Filled Elements" => $citationVars, "Empty Elements" => $citationVarsEmpty);
		$this->setData('citationVarArrays', $citationVarArrays);
                $this->setData('ts', time());
	}

	/**
	 * Fetch the form.
	 * @param $request Request
	 * @return the rendered form
	 */
	function fetch($request) {
		$citation =& $this->getCitation();
		assert(is_a($citation, 'Citation'));
		$namespacedMetadataProperties = $citation->getNamespacedMetadataProperties();

		// Add properties to the template (required for property display names)
		$templateMgr =& TemplateManager::getManager($request);
		$templateMgr->assign_by_ref('namespacedMetadataProperties', $namespacedMetadataProperties);

		// Add the citation to the template
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
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('editedCitation', 'citationState'));

		$citation =& $this->getCitation();
		$citationVars = array();
		foreach($citation->getSupportedMetadataAdapters() as $metadataAdapter) {
			// Retrieve the meta-data schema
			$metadataSchema =& $metadataAdapter->getMetadataSchema();
			$metadataSchemaNamespace = $metadataSchema->getNamespace();

			// Loop over the property names in the schema and add string
			// values for all form fields.
			$propertyNames =& $metadataSchema->getPropertyNames();
			foreach($propertyNames as $propertyName) {
				$citationVars[] = $metadataSchema->getNamespacedPropertyId($propertyName);
			}
		}
		$this->readUserVars($citationVars);
	}

	/**
	 * Save citation
	 */
	function execute() {
		$citation =& $this->getCitation();
		$citation->setEditedCitation($this->getData('editedCitation'));
		if (in_array($this->getData('citationState'), Citation::_getSupportedCitationStates())) {
			$citation->setCitationState($this->getData('citationState'));
		}

		// Extract data from citation form fields and inject it into the citation
		$metadataAdapters = $citation->getSupportedMetadataAdapters();
		foreach($metadataAdapters as $metadataAdapter) {
			// Instantiate a meta-data description for the given schema
			$metadataSchema =& $metadataAdapter->getMetadataSchema();
			import('metadata.MetadataDescription');
			$metadataDescription = new MetadataDescription($metadataSchema, ASSOC_TYPE_CITATION);

			// Set the meta-data statements
			$metadataSchemaNamespace = $metadataSchema->getNamespace();
			foreach($metadataSchema->getProperties() as $propertyName => $property) {
				$fieldName = $metadataSchema->getNamespacedPropertyId($propertyName);
				$fieldValue = trim($this->getData($fieldName));
				if (empty($fieldValue)) {
					$metadataDescription->removeStatement($propertyName);
				} else {
					$foundValidType = false;
					foreach($property->getTypes() as $type) {
						// Some property types need to be converted first
						switch($type) {
							// We currently only support name composites
							case array(METADATA_PROPERTY_TYPE_COMPOSITE => ASSOC_TYPE_AUTHOR):
							case array(METADATA_PROPERTY_TYPE_COMPOSITE => ASSOC_TYPE_EDITOR):
								import('metadata.nlm.PersonStringNlmNameSchemaFilter');
								$personStringFilter = new PersonStringNlmNameSchemaFilter($type[METADATA_PROPERTY_TYPE_COMPOSITE], PERSON_STRING_FILTER_MULTIPLE);
								assert($personStringFilter->supportsAsInput($fieldValue));
								$fieldValue =& $personStringFilter->execute($fieldValue);
								$foundValidType = true;
								break;

							case METADATA_PROPERTY_TYPE_INTEGER:
								$fieldValue = array((integer)$fieldValue);
								$foundValidType = true;
								break;

							case METADATA_PROPERTY_TYPE_DATE:
								import('metadata.DateStringNormalizerFilter');
								$dateStringFilter = new DateStringNormalizerFilter();
								assert($dateStringFilter->supportsAsInput($fieldValue));
								$fieldValue = array($dateStringFilter->execute($fieldValue));
								$foundValidType = true;
								break;

							default:
								if ($property->isValid($fieldValue)) {
									$fieldValue = array($fieldValue);
									$foundValidType = true;
									break;
								}
						}

						// Break the outer loop once we found a valid
						// interpretation for our form field.
						if ($foundValidType) break;
					}
					foreach($fieldValue as $fieldValueStatement) {
						$metadataDescription->addStatement($propertyName, $fieldValueStatement);
						unset($fieldValueStatement);
					}
				}
			}

			// Inject the meta-data into the citation
			$citation->injectMetadata($metadataDescription, true);
		}

		// Persist citation
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
