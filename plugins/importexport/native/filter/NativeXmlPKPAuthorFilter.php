<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlPKPAuthorFilter.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeXmlPKPAuthorFilter
 *
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a Native XML document to a set of authors
 */

namespace PKP\plugins\importexport\native\filter;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use PKP\author\contributorRole\ContributorRole;
use PKP\author\contributorRole\ContributorRoleIdentifier;
use PKP\author\contributorRole\ContributorType;
use PKP\facades\Locale;
use PKP\filter\FilterGroup;

class NativeXmlPKPAuthorFilter extends NativeImportFilter
{
    /**
     * Constructor
     *
     * @param FilterGroup $filterGroup
     */
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML author import');
        parent::__construct($filterGroup);
    }

    //
    // Implement template methods from NativeImportFilter
    //
    /**
     * Return the plural element name
     *
     * @return string
     */
    public function getPluralElementName()
    {
        return 'authors';
    }

    /**
     * Get the singular element name
     *
     * @return string
     */
    public function getSingularElementName()
    {
        return 'author';
    }

    /**
     * Handle an author element
     *
     * @param \DOMElement $node
     *
     * @return \PKP\author\Author
     */
    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $publication = $deployment->getPublication();
        assert($publication instanceof Publication);

        // Create the data object
        $author = Repo::author()->newDataObject();

        $author->setData('publicationId', $publication->getId());
        if ($node->getAttribute('primary_contact')) {
            $author->setPrimaryContact(true);
        }
        if ($node->getAttribute('include_in_browse')) {
            $author->setIncludeInBrowse(true);
        }
        if ($node->getAttribute('seq')) {
            $author->setSequence($node->getAttribute('seq'));
        }

        $author->setData('contributorType', $node->getAttribute('contributor_type') ?? ContributorType::PERSON->getName());
        if (!$node->getAttribute('contributor_type')) {
            $deployment->addError(
                Application::ASSOC_TYPE_SUBMISSION,
                $publication->getId(),
                __('plugins.importexport.common.error.missingContributorType')
            );
        }

        // Handle metadata in subelements
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof \DOMElement) {
                switch ($n->tagName) {
                    case 'givenname':
                        if ($author->getData('contributorType') === ContributorType::ANONYMOUS->getName()) {
                            break;
                        }

                        $locale = $n->getAttribute('locale');
                        if (empty($locale)) {
                            $locale = $publication->getData('locale');
                        }
                        if ($author->getData('contributorType') === ContributorType::PERSON->getName()) {
                            $author->setGivenName($n->textContent, $locale);
                        } elseif ($author->getData('contributorType') === ContributorType::ORGANIZATION->getName()) {
                            $author->setOrganizationName($n->textContent, $locale);
                        }
                        break;
                    case 'familyname':
                        if ($author->getData('contributorType') === ContributorType::PERSON->getName()) {
                            $locale = $n->getAttribute('locale');
                            if (empty($locale)) {
                                $locale = $publication->getData('locale');
                            }
                            $author->setFamilyName($n->textContent, $locale);
                        }
                        break;
                    case 'affiliation':
                        if ($author->getData('contributorType') === ContributorType::ANONYMOUS->getName()) {
                            break;
                        }

                        $affiliation = Repo::affiliation()->newDataObject();
                        for ($affiliationChildNode = $n->firstChild; $affiliationChildNode !== null; $affiliationChildNode = $affiliationChildNode->nextSibling) {
                            if ($affiliationChildNode instanceof \DOMElement) {
                                switch ($affiliationChildNode->tagName) {
                                    case 'name':
                                        $name = $affiliationChildNode->textContent;
                                        $ror = Repo::ror()->getCollector()->filterByName($name)->getMany()->first();
                                        if ($ror) {
                                            $affiliation->setRor($ror->getRor());
                                            $affiliation->setName(null);
                                            break;
                                        }
                                        $locale = $affiliationChildNode->getAttribute('locale');
                                        if (empty($locale)) {
                                            $locale = $publication->getData('locale');
                                        }
                                        $affiliation->setName($name, $locale);
                                        break;
                                }
                            }
                        }
                        $author->addAffiliation($affiliation);
                        break;
                    case 'rorAffiliation':
                        if ($author->getData('contributorType') === ContributorType::ANONYMOUS->getName()) {
                            break;
                        }

                        for ($rorAffiliationChildNode = $n->firstChild; $rorAffiliationChildNode !== null; $rorAffiliationChildNode = $rorAffiliationChildNode->nextSibling) {
                            if ($rorAffiliationChildNode instanceof \DOMElement) {
                                switch ($rorAffiliationChildNode->tagName) {
                                    case 'ror':
                                        $rorAffiliation = Repo::affiliation()->newDataObject();
                                        $rorAffiliation->setRor($rorAffiliationChildNode->textContent);
                                        $author->addAffiliation($rorAffiliation);
                                        break;
                                }
                            }
                        }
                        break;
                    case 'country': $author->setCountry($n->textContent);
                        break;
                    case 'email': $author->setEmail($n->textContent);
                        break;
                    case 'url':
                        if ($author->getData('contributorType') === ContributorType::ANONYMOUS->getName()) {
                            break;
                        }
                        $author->setUrl($n->textContent);
                        break;
                    case 'orcid':
                        if ($author->getData('contributorType') === ContributorType::ANONYMOUS->getName()) {
                            break;
                        }
                        $author->setOrcid($n->textContent);
                        break;
                    case 'biography':
                        if ($author->getData('contributorType') === ContributorType::ANONYMOUS->getName()) {
                            break;
                        }

                        $locale = $n->getAttribute('locale');
                        if (empty($locale)) {
                            $locale = $publication->getData('locale');
                        }
                        $author->setBiography($n->textContent, $locale);
                        break;
                    case 'contributor_roles':
                        $contributorRoleIds = [];
                        for ($contributorRoleChildNode = $n->firstChild; $contributorRoleChildNode !== null; $contributorRoleChildNode = $contributorRoleChildNode->nextSibling) {
                            if ($contributorRoleChildNode instanceof \DOMElement) {
                                $contributorRoleIds[] = (int) $contributorRoleChildNode->textContent;
                            }
                        }
                        $author->setContributorRoles(
                            ContributorRole::withContextId($context->getId())
                                ->withRoleIds($contributorRoleIds)
                                ->get()
                                ->all()
                        );
                        break;
                }
            }
        }

        $importAuthorId = $node->getAttribute('id');
        $authorName = $author->getLocalizedGivenName() ?? $author->getLocalizedOrganizationName() ?? $importAuthorId ?? '';

        $authorFullName = $author->getFullName(true, false, $publication->getData('locale'));
        if ($author->getData('contributorType') !== ContributorType::ANONYMOUS->getName() && empty($authorFullName)) {
            $deployment->addError(
                Application::ASSOC_TYPE_SUBMISSION,
                $publication->getId(),
                __('plugins.importexport.common.error.missingGivenName', [
                    'authorName' => $authorName,
                    'localeName' => Locale::getMetadata($publication->getData('locale'))->getDisplayName()
                ])
            );
        }

        foreach ($author->getAffiliations() as $affiliation) {
            if (!$affiliation->getRor() && !array_key_exists($publication->getData('locale'), $affiliation->getName())) {
                // Shell it be an error, or the affiliation could just be skipped ?
                $deployment->addError(
                    Application::ASSOC_TYPE_SUBMISSION,
                    $publication->getId(),
                    __('plugins.importexport.common.error.missingAffiliationName', [
                        'authorName' => $authorName,
                        'localeName' => Locale::getMetadata($publication->getData('locale'))->getDisplayName()
                    ])
                );
            }
        }

        if ($author->getData('contributorType') !== ContributorType::ANONYMOUS->getName() && !$author->getData('email')) {
            $deployment->addError(
                Application::ASSOC_TYPE_SUBMISSION,
                $publication->getId(),
                __('plugins.importexport.common.error.missingEmail', [
                    'authorName' => $authorName,
                ])
            );
        }

        if ($author->getData('contributorType') !== ContributorType::ANONYMOUS->getName() && !$author->getData('country')) {
            $deployment->addError(
                Application::ASSOC_TYPE_SUBMISSION,
                $publication->getId(),
                __('plugins.importexport.common.error.missingCountry', [
                    'authorName' => $authorName,
                ])
            );
        }

        if (!$author->getContributorRoles()) {
            $deployment->addError(
                Application::ASSOC_TYPE_AUTHOR,
                $publication->getId(),
                __('plugins.importexport.common.error.noContributorRole', [
                    'authorName' => $authorName
                ])
            );
            // Set first author role
            $author->setContributorRoles(
                ContributorRole::query()
                    ->withContextId($context->getId())
                    ->withIdentifier(ContributorRoleIdentifier::AUTHOR->getName())
                    ->limit(1)
                    ->get()
                    ->all()
            );
        }

        $authorId = Repo::author()->add($author);
        $author->setId($authorId);

        $deployment->setAuthorDBId($importAuthorId, $authorId);

        if ($node->getAttribute('id') == $publication->getData('primaryContactId')) {
            $publication->setData('primaryContactId', $author->getId());
        }

        return $author;
    }

    /**
     * Parse an identifier node
     *
     * @param \DOMElement $element
     * @param \PKP\author\Author $author
     */
    public function parseIdentifier($element, $author)
    {
        $deployment = $this->getDeployment();
        $publication = $deployment->getPublication();

        $advice = $element->getAttribute('advice');
        switch ($element->getAttribute('type')) {
            case 'internal':
                // "update" advice not supported yet.
                assert(!$advice || $advice == 'ignore');

                if ($element->textContent == $publication->getData('primaryContactId')) {
                    $publication->setData('primaryContactId', $author->getId());
                }

                break;
        }
    }
}
