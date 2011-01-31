<?php

/**
 * @file plugins/metadata/mods/filter/ModsSchemaSubmissionAdapter.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ModsSchemaSubmissionAdapter
 * @ingroup plugins_metadata_mods_filter
 * @see Submission
 * @see PKPModsSchema
 *
 * @brief Abstract base class for meta-data adapters that
 *  inject/extract MODS schema compliant meta-data into/from
 *  a Submission object.
 */

import('lib.pkp.classes.metadata.MetadataDataObjectAdapter');
import('lib.pkp.classes.metadata.nlm.NlmNameSchema');

class ModsSchemaSubmissionAdapter extends MetadataDataObjectAdapter {
	/**
	 * Constructor
	 */
	function ModsSchemaSubmissionAdapter($assocType) {
		// Configure the adapter
		parent::MetadataDataObjectAdapter('plugins.metadata.mods.schema.ModsSchema', 'lib.pkp.classes.submission.Submission', $assocType);
	}


	//
	// Implement template methods from MetadataDataObjectAdapter
	//
	/**
	 * @see MetadataDataObjectAdapter::injectMetadataIntoDataObject()
	 * @param $modsDescription MetadataDescription
	 * @param $submission Submission
	 * @param $replace boolean whether to replace the existing submission
	 * @param $authorClassName string the application specific author class name
	 */
	function &injectMetadataIntoDataObject(&$modsDescription, &$submission, $replace, $authorClassName) {
		if ($replace) $submission = new Submission();
		assert(is_a($submission, 'Submission'));
		assert($modsDescription->getMetadataSchemaName() == 'plugins.metadata.mods.schema.ModsSchema');

		// Get the cataloging language.
		$catalogingLanguage = $modsDescription->getStatement('recordInfo/languageOfCataloging/languageTerm[@authority="iso639-2b"]');
		$catalogingLocale = Locale::getLocaleFrom3LetterIso($catalogingLanguage);
		assert(!is_null($catalogingLocale));

		// Title
		$localizedTitles = $modsDescription->getStatementTranslations('titleInfo/title');
		if (is_array($localizedTitles)) {
			foreach($localizedTitles as $locale => $title) {
				$submission->setTitle($title, $locale);
			}
		}

		// Names: authors and sponsor
		$foundSponsor = false;
		$nameDescriptions =& $modsDescription->getStatement('name');
		if (is_array($nameDescriptions)) {
			foreach($nameDescriptions as $nameDescription) { /* @var $nameDescription MetadataDescription */
				// Check that we find the expected name schema.
				assert($nameDescription->getMetadataSchemaName() == 'lib.pkp.plugins.metadata.mods.schema.ModsNameSchema');

				// Retrieve the name type and role.
				$nameType = $nameDescription->getStatement('[@type]');
				$nameRoles = $nameDescription->getStatement('role/roleTerm[@type="code" @authority="marcrelator"]');

				// Transport the name into the submission depending
				// on name type and role.
				// FIXME: Move this to a dedicated adapter in the Author class.
				if (is_array($nameRoles)) {
					switch($nameType) {
						// Authors
						case 'personal':
							// Only authors go into the submission.
							if (in_array('aut', $nameRoles)) {
								// Instantiate a new author object.
								import($authorClassName);
								$author = new Author();

								// Family Name
								$author->setLastName($nameDescription->getStatement('namePart[@type="family"]'));

								// Given Names
								$givenNames = $nameDescription->getStatement('namePart[@type="given"]');
								if (!empty($givenNames)) {
									$givenNames = explode(' ', $givenNames, 2);
									if (isset($givenNames[0])) $author->setFirstName($givenNames[0]);
									if (isset($givenNames[1])) $author->setMiddleName($givenNames[1]);
								}

								// Affiliation
								// NB: Our MODS mapping currently doesn't support translation for names.
								// This can be added when required by data providers. We assume the cataloging
								// language for the record.
								$affiliation = $nameDescription->getStatement('affiliation');
								if (!empty($affiliation)) {
									$author->setAffiliation($affiliation, $catalogingLocale);
								}

								// Terms of address (unmapped field)
								$termsOfAddress = $nameDescription->getStatement('namePart[@type="termsOfAddress"]');
								if ($termsOfAddress) {
									$author->setData('nlm34:namePart[@type="termsOfAddress"]', $termsOfAddress);
								}

								// Date (unmapped field)
								$date = $nameDescription->getStatement('namePart[@type="date"]');
								if ($date) {
									$author->setData('nlm34:namePart[@type="date"]', $date);
								}

								// Add the author to the submission.
								$submission->addAuthor($author);
								unset($author);
							}
							break;

						// Sponsor
						// NB: Our MODS mapping currently doesn't support translation for names.
						// This can be added when required by data providers. We assume the cataloging
						// language for the record.
						case 'corporate':
							// Only the first sponsor goes into the submission.
							if (!$foundSponsor && in_array('spn', $nameRoles)) {
								$foundSponsor = true;
								$submission->setSponsor($nameDescription->getStatement('namePart'), $catalogingLocale);
							}
							break;
					}
				}

				unset($nameDescription);
			}
		}

		// Creation date
		$dateSubmitted = $modsDescription->getStatement('originInfo/dateCreated[@encoding="w3cdtf"]');
		if ($dateSubmitted) $submission->setDateSubmitted($dateSubmitted);

		// Submission language
		$submissionLanguage = $modsDescription->getStatement('language/languageTerm[@type="code" @authority="iso639-2b"]');
		$submissionLocale = Locale::get2LetterFrom3LetterIsoLanguage($submissionLanguage);
		if ($submissionLocale) {
			$submission->setLanguage($submissionLocale);
		}

		// Pages (extent)
		$pages = $modsDescription->getStatement('physicalDescription/extent');
		if ($pages) $submission->setPages($pages);

		// Abstract
		$localizedAbstracts = $modsDescription->getStatementTranslations('abstract');
		if (is_array($localizedAbstracts)) {
			foreach($localizedAbstracts as $locale => $abstract) {
				$submission->setAbstract($abstract, $locale);
			}
		}

		// Discipline, subject class and subject
		// FIXME: We currently ignore discipline, subject class and subject because we cannot
		// distinguish them within a list of MODS topic elements. Can we use several subject
		// statements with different authorities instead?

		// Geographical coverage
		$localizedCoverageGeos = $modsDescription->getStatementTranslations('subject/geographic');
		if (is_array($localizedCoverageGeos)) {
			foreach($localizedCoverageGeos as $locale => $localizedCoverageGeo) {
				$submission->setCoverageGeo($localizedCoverageGeo, $locale);
			}
		}

		// Chronological coverage
		$localizedCoverageChrons = $modsDescription->getStatementTranslations('subject/temporal');
		if (is_array($localizedCoverageChrons)) {
			foreach($localizedCoverageChrons as $locale => $localizedCoverageChron) {
				$submission->setCoverageChron($localizedCoverageChron, $locale);
			}
		}

		// Record identifier
		// NB: We currently don't override the submission id with the record identifier in MODS
		// to make sure that MODS records can be transported between different installations.

		// Handle unmapped fields.
		$this->injectUnmappedDataObjectMetadataFields($modsDescription, $submission);

		return $submission;
	}

	/**
	 * @see MetadataDataObjectAdapter::extractMetadataFromDataObject()
	 * @param $submission Submission
	 * @param $authorMarcrelatorRole string the marcrelator role to be used
	 *  for submission authors.
	 * @return MetadataDescription
	 */
	function &extractMetadataFromDataObject(&$submission, $authorMarcrelatorRole = 'aut') {
		assert(is_a($submission, 'Submission'));
		$modsDescription =& $this->instantiateMetadataDescription();

		// Retrieve the primary locale.
		$catalogingLocale = Locale::getPrimaryLocale();
		$catalogingLanguage = Locale::get3LetterIsoFromLocale($catalogingLocale);

		// Establish the association between the meta-data description
		// and the submission.
		$modsDescription->setAssocId($submission->getId());

		// Title
		$localizedTitles =& $submission->getTitle(null); // Localized
		$this->addLocalizedStatements($modsDescription, 'titleInfo/title', $localizedTitles);

		// Authors
		// FIXME: Move this to a dedicated adapter in the Author class.
		$authors =& $submission->getAuthors();
		foreach($authors as $author) { /* @var $author Author */
			// Create a new name description.
			$authorDescription = new MetadataDescription('lib.pkp.plugins.metadata.mods.schema.ModsNameSchema', ASSOC_TYPE_AUTHOR);

			// Type
			$authorType = 'personal';
			$authorDescription->addStatement('[@type]', $authorType);

			// Family Name
			$authorDescription->addStatement('namePart[@type="family"]', $author->getLastName());

			// Given Names
			$firstName = (string)$author->getFirstName();
			$middleName = (string)$author->getMiddleName();
			$givenNames = trim($firstName.' '.$middleName);
			if (!empty($givenNames)) {
				$authorDescription->addStatement('namePart[@type="given"]', $givenNames);
			}

			// Affiliation
			// NB: Our MODS mapping currently doesn't support translation for names.
			// This can be added when required by data consumers. We therefore only use
			// translations in the cataloging language.
			$affiliation = $author->getAffiliation($catalogingLocale);
			if ($affiliation) {
				$authorDescription->addStatement('affiliation', $affiliation);
			}

			// Terms of address (unmapped field)
			$termsOfAddress = $author->getData('nlm34:namePart[@type="termsOfAddress"]');
			if ($termsOfAddress) {
				$authorDescription->addStatement('namePart[@type="termsOfAddress"]', $termsOfAddress);
			}

			// Date (unmapped field)
			$date = $author->getData('nlm34:namePart[@type="date"]');
			if ($date) {
				$authorDescription->addStatement('namePart[@type="date"]', $date);
			}

			// Role
			$authorDescription->addStatement('role/roleTerm[@type="code" @authority="marcrelator"]', $authorMarcrelatorRole);

			// Add the author to the MODS schema.
			$modsDescription->addStatement('name', $authorDescription);
			unset($authorDescription);
		}

		// Sponsor
		// NB: Our MODS mapping currently doesn't support translation for names.
		// This can be added when required by data consumers. We therefore only use
		// translations in the cataloging language.
		$supportingAgency = $submission->getSponsor($catalogingLocale);
		if ($supportingAgency) {
			$supportingAgencyDescription = new MetadataDescription('lib.pkp.plugins.metadata.mods.schema.ModsNameSchema', ASSOC_TYPE_AUTHOR);
			$sponsorNameType = 'corporate';
			$supportingAgencyDescription->addStatement('[@type]', $sponsorNameType);
			$supportingAgencyDescription->addStatement('namePart', $supportingAgency);
			$sponsorRole = 'spn';
			$supportingAgencyDescription->addStatement('role/roleTerm[@type="code" @authority="marcrelator"]', $sponsorRole);
			$modsDescription->addStatement('name', $supportingAgencyDescription);
		}

		// Type of resource
		$typeOfResource = 'text';
		$modsDescription->addStatement('typeOfResource', $typeOfResource);

		// Creation & copyright date
		$submissionDate = $submission->getDateSubmitted();
		if (strlen($submissionDate) >= 4) {
			$modsDescription->addStatement('originInfo/dateCreated[@encoding="w3cdtf"]', $submissionDate);
			$modsDescription->addStatement('originInfo/copyrightDate[@encoding="w3cdtf"]', substr($submissionDate, 0, 4));
		}

		// Submission language
		$submissionLanguage = Locale::get3LetterFrom2LetterIsoLanguage($submission->getLanguage());
		if (!$submissionLanguage) {
			// Assume the cataloging language by default.
			$submissionLanguage = $catalogingLanguage;
		}
		$modsDescription->addStatement('language/languageTerm[@type="code" @authority="iso639-2b"]', $submissionLanguage);

		// Pages (extent)
		$modsDescription->addStatement('physicalDescription/extent', $submission->getPages());

		// Abstract
		$localizedAbstracts =& $submission->getAbstract(null); // Localized
		$this->addLocalizedStatements($modsDescription, 'abstract', $localizedAbstracts);

		// Discipline
		$localizedDisciplines = $submission->getDiscipline(null); // Localized
		$this->addLocalizedStatements($modsDescription, 'subject/topic', $localizedDisciplines);

		// Subject class
		$localizedSubjectClasses = $submission->getSubjectClass(null); // Localized
		$this->addLocalizedStatements($modsDescription, 'subject/topic', $localizedSubjectClasses);

		// Subject
		$localizedSubjects = $submission->getSubject(null); // Localized
		$this->addLocalizedStatements($modsDescription, 'subject/topic', $localizedSubjects);

		// Geographical coverage
		$localizedCoverageGeo = $submission->getCoverageGeo(null); // Localized
		$this->addLocalizedStatements($modsDescription, 'subject/geographic', $localizedCoverageGeo);

		// Chronological coverage
		$localizedCoverageChron = $submission->getCoverageChron(null); // Localized
		$this->addLocalizedStatements($modsDescription, 'subject/temporal', $localizedCoverageChron);

		// Record creation date
		$recordCreationDate = date('Y-m-d');
		$modsDescription->addStatement('recordInfo/recordCreationDate[@encoding="w3cdtf"]', $recordCreationDate);

		// Record identifier
		$modsDescription->addStatement('recordInfo/recordIdentifier[@source="pkp"]', $submission->getId());

		// Cataloging language
		$modsDescription->addStatement('recordInfo/languageOfCataloging/languageTerm[@authority="iso639-2b"]', $catalogingLanguage);

		// Handle unmapped fields.
		$this->extractUnmappedDataObjectMetadataFields($submission, $modsDescription);

		return $modsDescription;
	}

	/**
	 * @see MetadataDataObjectAdapter::getDataObjectMetadataFieldNames()
	 * @param $translated boolean
	 */
	function getDataObjectMetadataFieldNames($translated = true) {
		static $unmappedFields = false;

		if ($unmappedFields === false) {
			$metadataSchema =& $this->getMetadataSchema();
			$metadataSchemaNamespace = $metadataSchema->getNamespace();

			// The following properties have no mapping within this adapter.
			$unmappedFields = array(
				true => array(
					$metadataSchemaNamespace.':titleInfo/nonSort',
					$metadataSchemaNamespace.':titleInfo/subTitle',
					$metadataSchemaNamespace.':titleInfo/partNumber',
					$metadataSchemaNamespace.':titleInfo/partName',
					$metadataSchemaNamespace.':note'
				),
				false => array(
					$metadataSchemaNamespace.':subject/temporal[@encoding="w3cdtf" @point="start"]',
					$metadataSchemaNamespace.':subject/temporal[@encoding="w3cdtf" @point="end"]'
				)
			);
		}

		return ($unmappedFields[$translated]);
	}
}
?>
