<?php

/**
 * @file classes/controllers/grid/citation/form/CitationForm.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationForm
 * @ingroup classes_controllers_grid_citation_form
 *
 * @brief Form for adding/editing a citation.
 */

import('lib.pkp.classes.form.Form');

define('CITATION_FORM_FULL_TEMPLATE', 'controllers/grid/citation/form/citationForm.tpl');
define('CITATION_FORM_COMPARISON_TEMPLATE', 'controllers/grid/citation/form/citationFormErrorsAndComparison.tpl');

class CitationForm extends Form {
	/** @var Citation the citation being edited */
	var $_citation;

	/** @var DataObject the object the citation belongs to */
	var $_assocObject;

	/** @var boolean */
	var $_unsavedChanges;

	/** @var array all properties contained in the citation */
	var $_citationProperties;

	/**
	 * @var array a list of meta-data descriptions with the
	 *  citation meta-data extracted from the the form citation
	 *  (in initData()) or the post request (in readInputData()).
	 */
	var $_metadataDescriptions = array();

	/**
	 * Constructor.
	 * @param $request PKPRequest
	 * @param $citation Citation
	 * @param $assocObject DataObject
	 */
	function CitationForm(&$request, &$citation, &$assocObject) {
		parent::Form();
		assert(is_a($citation, 'Citation'));
		assert(is_a($assocObject, 'DataObject'));

		$this->_citation =& $citation;
		$this->_assocObject =& $assocObject;

		// Identify all form field names for the citation
		$this->_citationFormFieldNames = array();
		foreach($citation->getSupportedMetadataSchemas() as $metadataSchema) {
			// Loop over the properties names in the schema and save
			// them in a flat list.
			$properties = $metadataSchema->getProperties();
			foreach($properties as $property) {
				$this->_citationProperties[$metadataSchema->getNamespacedPropertyId($property->getName())] =& $property;
				unset($property);
			}
		}

		// Validation checks for this form that are not checked within the default meta-data validation algorithm.
		$this->addCheck(new FormValidator($this, 'rawCitation', 'required', 'submission.citations.editor.details.rawCitationRequired'));
		$this->addCheck(new FormValidatorPost($this));
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
	 * Get the object the citation belongs to.
	 * @return DataObject
	 */
	function &getAssocObject() {
		return $this->_assocObject;
	}

	/**
	 * Set true if the form contains unsaved changes.
	 * @param $unsavedChanges boolean
	 */
	function setUnsavedChanges($unsavedChanges) {
		return $this->_unsavedChanges = (boolean)$unsavedChanges;
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
		// Make sure that this method is not called twice which
		// would corrupt internal state.
		assert(empty($this->_metadataDescriptions));

		$citation =& $this->getCitation();

		// The unparsed citation text and the citation state
		$this->setData('rawCitation', $citation->getRawCitation());

		// Citation meta-data
		foreach($citation->getSupportedMetadataSchemas() as $metadataSchema) {
			// Loop over the properties in the schema and add string
			// values for all form fields.
			$properties = $metadataSchema->getProperties();
			$metadataDescription =& $citation->extractMetadata($metadataSchema);

			// Save the meta-data description for later usage.
			$this->_metadataDescriptions[] = $metadataDescription;

			foreach($properties as $propertyName => $property) {
				if ($metadataDescription->hasStatement($propertyName)) {
					$value = $metadataDescription->getStatement($propertyName);
					$fieldValue = $this->_getStringValueFromMetadataStatement($property, $value);
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
		$this->readUserVars(array('rawCitation', 'citationFilters', 'citationState', 'citationApproved'));

		// Read citation meta-data properties.
		$this->readUserVars(array_keys($this->_citationProperties));
	}

	/**
	 * Custom implementation of Form::validate() that validates
	 * meta-data form data and injects it into the internal citation
	 * object.
	 *
	 * NB: The configuration of the internal citation object
	 * would normally be done in readInputData(). Validation and
	 * injection can easily be done in one step. It therefore avoids
	 * code duplication and improves performance to do both here.
	 */
	function validate() {
		// Make sure that this method is not called twice which
		// would corrupt internal state.
		assert(empty($this->_metadataDescriptions));

		parent::validate();

		// Validate form data and inject it into
		// the associated citation object.
		$citation =& $this->getCitation();
		$citation->setRawCitation($this->getData('rawCitation'));
		if ($this->getData('citationApproved') == 'citationApproved') {
			// Editor's shortcut to the approved state, e.g. for manually edited citations.
			$citation->setCitationState(CITATION_APPROVED);
		} elseif (in_array($this->getData('citationState'), Citation::_getSupportedCitationStates())) {
			// Reset citation state if necessary
			if ($this->getData('citationState') == CITATION_APPROVED) $this->setData('citationState', CITATION_LOOKED_UP);
			$citation->setCitationState($this->getData('citationState'));
		}

		// Extract data from citation form fields and inject it into the citation
		import('lib.pkp.classes.metadata.MetadataDescription');
		$metadataSchemas = $citation->getSupportedMetadataSchemas();
		foreach($metadataSchemas as $metadataSchema) { /* @var $metadataSchema MetadataSchema */
			// Instantiate a meta-data description for the given schema
			$metadataDescription = new MetadataDescription($metadataSchema->getClassName(), ASSOC_TYPE_CITATION);

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
						$this->addError($fieldName, __($property->getValidationMessage()));
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
							import('lib.pkp.plugins.metadata.nlm30.filter.PersonStringNlm30NameSchemaFilter');
							$personStringFilter = new PersonStringNlm30NameSchemaFilter($assocType, PERSON_STRING_FILTER_MULTIPLE);
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
							$this->addError($fieldName, __($property->getValidationMessage()));
							$this->addErrorField($fieldName);
						}
						unset($typedFieldValue);
					}
					unset($typedFieldValues);
				}
			}

			// Inject the meta-data into the citation.
			$citation->injectMetadata($metadataDescription);

			// Save the meta-data description for later usage.
			$this->_metadataDescriptions[] =& $metadataDescription;

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
		$citationDao =& DAORegistry::getDAO('CitationDAO');
		if (is_numeric($citation->getId())) {
			$citationDao->updateObject($citation);
		} else {
			$citationDao->insertObject($citation);
		}
		return true;
	}


	/**
	 * Fetch the form.
	 * @param $request Request
	 * @param $template string the template to render the form
	 * @return string the rendered form
	 */
	function fetch($request, $template = CITATION_FORM_FULL_TEMPLATE) {
		// Instantiate objects used throughout.
		$user =& $request->getUser();
		$router =& $request->getRouter();
		$context =& $router->getContext($request);
		$citation =& $this->getCitation();
		$assocObject =& $this->getAssocObject();
		$citationDao =& DAORegistry::getDAO('CitationDAO'); /* @var $citationDao CitationDAO */

		/////////////////////////////////////////////////////
		// Raw citation editing and citation comparison
		// (comparison template and full template):
		//
		// 1) Messages
		//
		// Add the citation to the template.
		$templateMgr =& TemplateManager::getManager($request);
		$templateMgr->assign_by_ref('citation', $citation);

		// Does the form contain unsaved changes?
		$templateMgr->assign('unsavedChanges', $this->getUnsavedChanges());

		if ($citation->getId()) {
			//
			// 2) Citation output preview
			//
			// Either the initData() or validate() method should have prepared
			// a meta-data representation of the citation.
			// NB: Our template and output filters currently only handle
			// one meta-data description. Any others but the first one are ignored.
			assert(!empty($this->_metadataDescriptions));
			$metadataDescription = array_pop($this->_metadataDescriptions);

			// Generate the formatted citation output from the description.
			$citationOutputFilter =& $citationDao->instantiateCitationOutputFilter($context);
			$generatedCitation = $citationOutputFilter->execute($metadataDescription);
			foreach($citationOutputFilter->getErrors() as $citationGenerationError) {
				$this->addError('rawCitation', $citationGenerationError);
			}
			$citationOutputFilter->clearErrors();

			// Strip formatting and the Google Scholar tag so that we get a plain
			// text string that is comparable with the raw citation.
			$generatedCitation = trim(str_replace(GOOGLE_SCHOLAR_TAG, '', strip_tags($generatedCitation)));

			// Compare the raw and the formatted citation and add the result to the template.
			$citationDiff = String::diff($this->getData('rawCitation'), $generatedCitation);
			$templateMgr->assign('citationDiff', $citationDiff);
			$templateMgr->assign('currentOutputFilter', $citationOutputFilter->getDisplayName());

			//
			// 3) Raw citation editing
			//
			// Retrieve all available citation filters
			$availableParserFilters =& $citationDao->getCitationFilterInstances($context->getId(), CITATION_PARSER_FILTER_GROUP, array(), true);
			$templateMgr->assign_by_ref('availableParserFilters', $availableParserFilters);
			$availableLookupFilters =& $citationDao->getCitationFilterInstances($context->getId(), CITATION_LOOKUP_FILTER_GROUP, array(), true);
			$templateMgr->assign_by_ref('availableLookupFilters', $availableLookupFilters);

			// Did the user disable the raw citation editing warning?
			$userSettingsDao =& DAORegistry::getDAO('UserSettingsDAO');
			$rawCitationEditingWarningHide = (boolean)$userSettingsDao->getSetting($user->getId(), 'citation-editor-hide-raw-editing-warning');
			$templateMgr->assign('rawCitationEditingWarningHide', $rawCitationEditingWarningHide);
		}

		if ($template == CITATION_FORM_FULL_TEMPLATE) {
			/////////////////////////////////////////////////////
			// Citation improvement options
			// (full template only):
			//
			// 1) Manual editing
			//
			// Available fields
			$availableFields = array();
			foreach($this->_citationProperties as $fieldName => $property) {
				// Prepare property information for the view.
				$availableFields[$fieldName] = array(
					'displayName' => __($property->getDisplayName()),
					'required' => $property->getMandatory()?'true':'false'
				);

				// In the case of vocabulary based properties: Retrieve
				// the vocabulary to be displayed as a drop down.
				$availableFields[$fieldName]['options'] = null;
				$controlledVocabDao = null;
				foreach($property->getAllowedTypes() as $allowedType => $allowedTypeParams) {
					if ($allowedType == METADATA_PROPERTY_TYPE_VOCABULARY) {
						assert(count($allowedTypeParams) == 1);
						$vocabName = $allowedTypeParams[0];
						$vocabNameParts = explode(':', $vocabName);
						$vocabNamePartsCount = count($vocabNameParts);
						assert($vocabNamePartsCount == 1 || $vocabNamePartsCount == 3);
						if ($vocabNamePartsCount == 1) {
							// assume a site-wide vocabulary
							$symbolic = $vocabName;
							$assocType = $assocId = 0;
						} else {
							// assume a context-specific vocabulary
							list($symbolic, $assocType, $assocId) = $vocabNameParts;
						}

						// Enumerate the identified vocabulary.
						if (is_null($controlledVocabDao)) {
							$controlledVocabDao =& DAORegistry::getDAO('ControlledVocabDAO');
						}
						$availableFields[$fieldName]['options'] = $controlledVocabDao->enumerateBySymbolic($symbolic, $assocType, $assocId);
					}
				}
			}

			// Sort available fields alphabetically.
			$availableFields = $this->_orderByDisplayName($availableFields);

			$templateMgr->assign_by_ref('availableFields', $availableFields);

			//
			// 2) Citation Services Query
			//
			// Nothing to do: Lookup filters have already been assigned for
			// raw citation editing (see above).

			//
			// 3) Google Scholar
			//
			// Nothing to do.

			//
			// 4) Author Query
			//
			// Add the author query text to the template.
			$author =& $assocObject->getUser();
			$user =& $request->getUser();
			$emailParams = array(
				'authorFirstName' => strip_tags($author->getFirstName()),
				'authorLastName' => strip_tags($author->getLastName()),
				'userFirstName' => strip_tags($user->getFirstName()),
				'userLastName' => strip_tags($user->getLastName()),
				'articleTitle' => strip_tags($assocObject->getLocalizedTitle()),
				'rawCitation' => strip_tags($citation->getRawCitation())
			);
			import('classes.mail.MailTemplate');
			$mail = new MailTemplate('CITATION_EDITOR_AUTHOR_QUERY', null, false, null, true, true);
			$mail->assignParams($emailParams);
			$templateMgr->assign('authorQuerySubject', $mail->getSubject());
			$templateMgr->assign('authorQueryBody', $mail->getBody());


			/////////////////////////////////////////////////////
			// Expert Citation Services Results
			// (full template only):
			//
			// Citation source tabs
			$citationSourceTabs = array();
			$locale = AppLocale::getLocale();

			// Run through all source descriptions and extract statements.
			$sourceDescriptions =& $citation->getSourceDescriptions();
			assert(is_array($sourceDescriptions));
			foreach($sourceDescriptions as $sourceDescription) {
				$sourceDescriptionId = $sourceDescription->getId();
				$metadataSchema =& $sourceDescription->getMetadataSchema();

				// Use the display name of the description for the tab.
				// We can safely use the 'displayName' key here as
				// the keys representing statements will be namespaced.
				$citationSourceTabs[$sourceDescriptionId]['displayName'] = $sourceDescription->getDisplayName();

				// Prepare the description's statements as a sub-array.
				$thisDescription = array();
				foreach ($sourceDescription->getStatements() as $propertyName => $value) {
					$property =& $metadataSchema->getProperty($propertyName);

					// Handle translation
					if ($property->getTranslated()) {
						assert(isset($value[$locale]));
						$value = $value[$locale];
					}

					$sourcePropertyId = $sourceDescriptionId.'-'.$metadataSchema->getNamespacedPropertyId($propertyName);
					$sourcePropertyValue = $this->_getStringValueFromMetadataStatement($property, $value);
					$thisDescription[$sourcePropertyId] = array(
						'displayName' => $property->getDisplayName(),
						'value' => $sourcePropertyValue
					);
				}
				// Order description properties in the sub-array by display name.
				$thisDescription = $this->_orderByDisplayName($thisDescription);

				// Add the description sub-array to the descriptons list.
				$citationSourceTabs[$sourceDescriptionId]['statements'] = $thisDescription;
				unset($thisDescription);

				// Remove source descriptions that don't have data.
				if (!isset($citationSourceTabs[$sourceDescriptionId]['statements'])) unset($citationSourceTabs[$sourceDescriptionId]);
			}
			$templateMgr->assign_by_ref('citationSourceTabs', $citationSourceTabs);

			/////////////////////////////////////////////////////
			// Form level actions
			// (full template only):
			//
			// Set the approval state.
			$citationApproved = ($citation->getCitationState() == CITATION_APPROVED ? true : false);
			$templateMgr->assign('citationApproved', $citationApproved);

			// Auto-add client-side validation
			// FIXME: Move to JS framework's AjaxFormHandler, also see #6442
			$templateMgr->assign('validateId', 'citationForm');
		}

		return parent::fetch($request, $template);
	}

	//
	// Private helper methods
	//
	/**
	 * Take a structured meta-data statement and transform it into a
	 * plain text value that can be displayed to the end-user.
	 *
	 * @param $property MetadataProperty
	 * @param $value mixed
	 * @return string
	 */
	function _getStringValueFromMetadataStatement(&$property, &$value) {
		if ($property->getCardinality() == METADATA_PROPERTY_CARDINALITY_MANY && !empty($value)) {
			$allowedTypes = $property->getAllowedTypes();
			if (isset($allowedTypes[METADATA_PROPERTY_TYPE_COMPOSITE])) {
				// We currently only can transform composite
				// name arrays to strings.
				$allowedAssocTypes = $allowedTypes[METADATA_PROPERTY_TYPE_COMPOSITE];
				assert(in_array(ASSOC_TYPE_AUTHOR, $allowedAssocTypes) || in_array(ASSOC_TYPE_EDITOR, $allowedAssocTypes));
				import('lib.pkp.plugins.metadata.nlm30.filter.Nlm30NameSchemaPersonStringFilter');
				$personStringFilter = new Nlm30NameSchemaPersonStringFilter(PERSON_STRING_FILTER_MULTIPLE);
				assert($personStringFilter->supportsAsInput($value));
				$stringValue = $personStringFilter->execute($value);
			} else {
				// We currently can't transform properties of
				// cardinality "many" to strings.
				assert(is_array($value) && count($value) <= 1);
				$stringValue = $value[0];
			}
		} else {
			$stringValue = (string)$value;
		}

		return $stringValue;
	}

	/**
	 * Alphabetically order the given field list by display
	 * name.
	 * @param $fieldList array expects an array of entries
	 *  with a sub-key "displayName".
	 * @return array the ordered field.
	 */
	function _orderByDisplayName($fieldList) {
		uasort($fieldList, create_function('$a, $b', 'return strcmp($a["displayName"], $b["displayName"]);'));
		return $fieldList;
	}
}

?>
