<?php

/**
 * @file plugins/metadata/mods/filter/ModsSchemaSubmissionAdapter.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
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
		$modsSchema =& $modsDescription->getMetadataSchema();

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
								if (isset($givenNames[0])) $author->setFirstName($givenNames[0]);
								if (isset($givenNames[1])) $author->setMiddleName($givenNames[1]);

								// Affiliation
								$localizedAffiliations = $nameDescription->getStatementTranslations('affiliation');
								if (!empty($localizedAffiliation)) {
									// We can only use one affiliation as our MODS mapping cannot
									// provide translation support for affiliations.
									$primaryLocale = Locale::getPrimaryLocale();
									$currentLocale = Locale::getLocale();
									if (isset($localizedAffiliations[$primaryLocale])) {
										$affiliation = $localizedAffiliations[$primaryLocale];
									} elseif (isset($localizedAffiliations[$currentLocale])) {
										$affiliation = $localizedAffiliations[$currentLocale];
									} else {
										$affiliation = $localizedAffiliations[0];
									}
									$author->setAffiliation($affiliation, $locale);
								}

								// Add the author to the submission.
								$submission->addAuthor($author);
								unset($author);
							}
							break;

						// Sponsor
						case 'corporate':
							// Only the first sponsor goes into the submission.
							if (!$foundSponsor && in_array('spn', $nameRoles)) {
								$foundSponsor = true;
								$submission->setSponsor($nameDescription->getStatement('namePart'));
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
		$submissionLanguages = $modsSchema->get2LetterFrom3LetterIsoLanguage($modsDescription->getStatement('language[@objectPart=""]/languageTerm[@type="code" @authority="iso639-2b"]'));
		if (is_array($submissionLanguages) && isset($submissionLanguages[0])) {
			$submission->setLanguage($submissionLanguages[0]);
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

		return $submission;
	}

	/**
	 * @see MetadataDataObjectAdapter::extractMetadataFromDataObject()
	 * @param $submission Submission
	 * @param $authorMarcrelatorRole string the marcrelator role to be used
	 *  for submission authors.
	 */
	function &extractMetadataFromDataObject(&$submission, $authorMarcrelatorRole = 'aut') {
		assert(is_a($submission, 'Submission'));
		$modsDescription =& $this->instantiateMetadataDescription();
		$modsSchema =& $modsDescription->getMetadataSchema();

		// Retrieve the primary locale.
		$primaryLocale = Locale::getPrimaryLocale();
		$primaryLanguage = $modsSchema->get3LetterIsoFromLocale($primaryLocale);

		// Establish the association between the meta-data description
		// and the submission.
		$modsDescription->setAssocId($submission->getId());

		// Title
		$localizedTitles =& $submission->getTitle(null); // Localized
		$this->addLocalizedStatements($modsDescription, 'titleInfo/title', $localizedTitles);

		// Authors
		$authors =& $submission->getAuthors();
		foreach($authors as $author) {
			// Create a new name description.
			$authorDescription = new MetadataDescription('lib.pkp.plugins.metadata.mods.schema.ModsNameSchema', ASSOC_TYPE_AUTHOR);

			// Type
			$authorType = 'personal';
			$authorDescription->addStatement('[@type]', $authorType);

			// Family Name
			$authorDescription->addStatement('namePart[@type="family"]', $author->getLastName());

			// Given Names
			$authorDescription->addStatement('namePart[@type="given"]', $author->getFirstName());
			$middleName = $author->getMiddleName();
			if (!empty($middleName)) {
				$authorDescription->addStatement('namePart[@type="given"]', $middleName);
			}

			// Affiliation
			$localizedAffiliation =& $author->getAffiliation(null); // Localized
			$this->addLocalizedStatements($authorDescription, 'affiliation', $localizedAffiliation);

			// Role
			$authorDescription->addStatement('role/roleTerm[@type="code" @authority="marcrelator"]', $authorMarcrelatorRole);

			// Add the author to the MODS schema.
			$modsDescription->addStatement('name', $authorDescription);
			unset($authorDescription);
		}

		// Sponsor
		$supportingAgency = $submission->getSponsor($primaryLanguage); // Try the cataloging language first.
		if (!$supportingAgency) {
			$supportingAgency = $submission->getLocalizedSponsor();
		}
		if ($supportingAgency) {
			$supportingAgencyDescription = new MetadataDescription('lib.pkp.plugins.metadata.mods.schema.ModsNameSchema', ASSOC_TYPE_AUTHOR);
			$supportingAgencyDescription->addStatement('[@type]', 'corporate');
			$supportingAgencyDescription->addStatement('namePart', $supportingAgency);
			$supportingAgencyDescription->addStatement('role/roleTerm[@type="code" @authority="marcrelator"]', 'spn');
			$modsDescription->addStatement('name', $supportingAgencyDescription);
		}

		// Type of resource
		$typeOfResource = 'text';
		$modsDescription->addStatement('typeOfResource', $typeOfResource);

		// Creation date
		$modsDescription->addStatement('originInfo/dateCreated[@encoding="w3cdtf"]', $submission->getDateSubmitted());

		// Submission language
		$submissionLanguage = $modsSchema->get3LetterFrom2LetterIsoLanguage($submission->getLanguage());
		if (!$submissionLanguage) {
			$submissionLanguage = $primaryLanguage;
		}
		$modsDescription->addStatement('language[@objectPart=""]/languageTerm[@type="code" @authority="iso639-2b"]', $submissionLanguage);

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
		$recordCreationDate = date('%Y-%m-%d');
		$modsDescription->addStatement('recordInfo/recordCreationDate[@encoding="w3cdtf"]', $recordCreationDate);

		// Record identifier
		$modsDescription->addStatement('recordInfo/recordIdentifier[@source="pkp"]', $submission->getId());

		// Cataloging language
		$modsDescription->addStatement('recordInfo/languageOfCataloging/languageTerm[@authority="iso639-2b"]', $primaryLanguage);

		return $modsDescription;
	}
}
?>
