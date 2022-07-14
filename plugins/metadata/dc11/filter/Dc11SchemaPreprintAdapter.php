<?php

/**
 * @file plugins/metadata/dc11/filter/Dc11SchemaPreprintAdapter.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Dc11SchemaPreprintAdapter
 * @ingroup plugins_metadata_dc11_filter
 *
 * @see Preprint
 * @see PKPDc11Schema
 *
 * @brief Abstract base class for meta-data adapters that
 *  injects/extracts Dublin Core schema compliant meta-data into/from
 *  a Submission object.
 */

namespace APP\plugins\metadata\dc11\filter;

use PKP\plugins\HookRegistry;
use APP\core\Application;
use APP\facades\Repo;
use APP\oai\ops\OAIDAO;
use APP\plugins\PubIdPlugin;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\i18n\LocaleConversion;
use PKP\metadata\MetadataDataObjectAdapter;
use PKP\metadata\MetadataDescription;
use PKP\plugins\HookRegistry;
use PKP\plugins\PluginRegistry;

class Dc11SchemaPreprintAdapter extends MetadataDataObjectAdapter
{
    //
    // Implement template methods from Filter
    //
    /**
     * @see Filter::getClassName()
     */
    public function getClassName()
    {
        return 'plugins.metadata.dc11.filter.Dc11SchemaPreprintAdapter';
    }


    //
    // Implement template methods from MetadataDataObjectAdapter
    //
    /**
     * @see MetadataDataObjectAdapter::injectMetadataIntoDataObject()
     *
     * @param MetadataDescription $metadataDescription
     * @param Preprint $targetDataObject
     */
    public function &injectMetadataIntoDataObject(&$metadataDescription, &$targetDataObject)
    {
        // Not implemented
        assert(false);
    }

    /**
     * @see MetadataDataObjectAdapter::extractMetadataFromDataObject()
     *
     * @param Submission $submission
     *
     * @return MetadataDescription
     */
    public function &extractMetadataFromDataObject(&$submission)
    {
        assert($submission instanceof \APP\submission\Submission);

        // Retrieve data that belongs to the submission.
        // FIXME: Retrieve this data from the respective entity DAOs rather than
        // from the OAIDAO once we've migrated all OAI providers to the
        // meta-data framework. We're using the OAIDAO here because it
        // contains cached entities and avoids extra database access if this
        // adapter is called from an OAI context.
        $oaiDao = DAORegistry::getDAO('OAIDAO'); /** @var OAIDAO $oaiDao */
        $server = $oaiDao->getServer($submission->getData('contextId'));
        $section = $oaiDao->getSection($submission->getSectionId());

        $dc11Description = $this->instantiateMetadataDescription();

        // Title
        $this->_addLocalizedElements($dc11Description, 'dc:title', $submission->getFullTitle(null));

        // Creator
        $authors = Repo::author()->getSubmissionAuthors($submission);

        foreach ($authors as $author) {
            $dc11Description->addStatement('dc:creator', $author->getFullName(false, true));
        }

        // Subject
        $submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
        $submissionSubjectDao = DAORegistry::getDAO('SubmissionSubjectDAO');
        $supportedLocales = array_keys(Locale::getSupportedFormLocales());
        $subjects = array_merge_recursive(
            (array) $submissionKeywordDao->getKeywords($submission->getCurrentPublication()->getId(), $supportedLocales),
            (array) $submissionSubjectDao->getSubjects($submission->getCurrentPublication()->getId(), $supportedLocales)
        );
        $this->_addLocalizedElements($dc11Description, 'dc:subject', $subjects);

        // Description
        $this->_addLocalizedElements($dc11Description, 'dc:description', $submission->getAbstract(null));

        // Publisher
        $publisherInstitution = $server->getData('publisherInstitution');
        if (!empty($publisherInstitution)) {
            $publishers = [$server->getPrimaryLocale() => $publisherInstitution];
        } else {
            $publishers = $server->getName(null); // Default
        }
        $this->_addLocalizedElements($dc11Description, 'dc:publisher', $publishers);

        // Contributor
        $contributors = (array) $submission->getSponsor(null);
        foreach ($contributors as $locale => $contributor) {
            $contributors[$locale] = array_map('trim', explode(';', $contributor));
        }
        $this->_addLocalizedElements($dc11Description, 'dc:contributor', $contributors);


        // Date
        if ($submission->getDatePublished()) {
            $dc11Description->addStatement('dc:date', date('Y-m-d', strtotime($submission->getDatePublished())));
        }

        // Type
        $driverType = 'info:eu-repo/semantics/preprint';
        $dc11Description->addStatement('dc:type', $driverType, MetadataDescription::METADATA_DESCRIPTION_UNKNOWN_LOCALE);
        $driverVersion = 'info:eu-repo/semantics/draft';
        $dc11Description->addStatement('dc:type', $driverVersion, MetadataDescription::METADATA_DESCRIPTION_UNKNOWN_LOCALE);

        $galleys = Repo::galley()->getMany(
            Repo::galley()
                ->getCollector()
                ->filterByPublicationIds([$submission->getCurrentPublication()->getId()])
        );

        // Format
        foreach ($galleys as $galley) {
            $dc11Description->addStatement('dc:format', $galley->getFileType());
        }

        // Identifier: URL
        $request = Application::get()->getRequest();
        $includeUrls = $server->getSetting('publishingMode') != \APP\server\Server::PUBLISHING_MODE_NONE;
        $dc11Description->addStatement('dc:identifier', $request->url($server->getPath(), 'preprint', 'view', [$submission->getBestId()]));

        // Language
        $locales = [];
        foreach ($galleys as $galley) {
            $galleyLocale = $galley->getLocale();
            if (!is_null($galleyLocale) && !in_array($galleyLocale, $locales)) {
                $locales[] = $galleyLocale;
                $dc11Description->addStatement('dc:language', LocaleConversion::getIso3FromLocale($galleyLocale));
            }
        }
        $submissionLanguage = $submission->getLanguage();
        if (empty($locales) && !empty($submissionLanguage)) {
            $dc11Description->addStatement('dc:language', strip_tags($submissionLanguage));
        }

        // Relation
        // full text URLs
        if ($includeUrls) {
            foreach ($galleys as $galley) {
                $relation = $request->url($server->getPath(), 'preprint', 'view', [$submission->getBestId(), $galley->getBestGalleyId()]);
                $dc11Description->addStatement('dc:relation', $relation);
            }
        }

        // Public identifiers
        $publicIdentifiers = [
            'doi',
            ...array_map(fn (PubIdPlugin $plugin) => $plugin->getPubIdType(), (array) PluginRegistry::loadCategory('pubIds', true, $submission->getId()))
        ];
        foreach ($publicIdentifiers as $publicIdentifier) {
            if ($pubPreprintId = $submission->getStoredPubId($publicIdentifier)) {
                $dc11Description->addStatement('dc:identifier', $pubPreprintId);
            }
            foreach ($galleys as $galley) {
                if ($pubGalleyId = $galley->getStoredPubId($publicIdentifier)) {
                    $dc11Description->addStatement('dc:relation', $pubGalleyId);
                }
            }
        }

        // Coverage
        $this->_addLocalizedElements($dc11Description, 'dc:coverage', (array) $submission->getCoverage(null));

        // Rights: Add both copyright statement and license
        $copyrightHolder = $submission->getLocalizedCopyrightHolder();
        $copyrightYear = $submission->getCopyrightYear();
        if (!empty($copyrightHolder) && !empty($copyrightYear)) {
            $dc11Description->addStatement('dc:rights', __('submission.copyrightStatement', ['copyrightHolder' => $copyrightHolder, 'copyrightYear' => $copyrightYear]));
        }
        if ($licenseUrl = $submission->getLicenseURL()) {
            $dc11Description->addStatement('dc:rights', $licenseUrl);
        }

        HookRegistry::call('Dc11SchemaPreprintAdapter::extractMetadataFromDataObject', [$this, $submission, $server, &$dc11Description]);

        return $dc11Description;
    }

    /**
     * @see MetadataDataObjectAdapter::getDataObjectMetadataFieldNames()
     *
     * @param bool $translated
     */
    public function getDataObjectMetadataFieldNames($translated = true)
    {
        // All DC fields are mapped.
        return [];
    }


    //
    // Private helper methods
    //
    /**
     * Add an array of localized values to the given description.
     *
     * @param MetadataDescription $description
     * @param string $propertyName
     * @param array $localizedValues
     */
    public function _addLocalizedElements(&$description, $propertyName, $localizedValues)
    {
        foreach (stripAssocArray((array) $localizedValues) as $locale => $values) {
            if (is_scalar($values)) {
                $values = [$values];
            }
            foreach ($values as $value) {
                if (!empty($value)) {
                    $description->addStatement($propertyName, $value, $locale);
                }
            }
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\metadata\dc11\filter\Dc11SchemaPreprintAdapter', '\Dc11SchemaPreprintAdapter');
}
