<?php

/**
 * @file plugins/importexport/native/filter/NativeXmlPKPAuthorFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
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
use Exception;
use PKP\author\Author;
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

        // Handle metadata in subelements
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof \DOMElement) {
                switch ($n->tagName) {
                    case 'givenname':
                        $locale = $n->getAttribute('locale');
                        if (empty($locale)) {
                            $locale = $publication->getData('locale');
                        }
                        $author->setGivenName($n->textContent, $locale);
                        break;
                    case 'familyname':
                        $locale = $n->getAttribute('locale');
                        if (empty($locale)) {
                            $locale = $publication->getData('locale');
                        }
                        $author->setFamilyName($n->textContent, $locale);
                        break;
                    case 'affiliation':
                        $locale = $n->getAttribute('locale');
                        if (empty($locale)) {
                            $locale = $publication->getData('locale');
                        }
                        $author->setAffiliation($n->textContent, $locale);
                        break;
                    case 'country': $author->setCountry($n->textContent);
                        break;
                    case 'email': $author->setEmail($n->textContent);
                        break;
                    case 'url': $author->setUrl($n->textContent);
                        break;
                    case 'orcid': $author->setOrcid($n->textContent);
                        break;
                    case 'biography':
                        $locale = $n->getAttribute('locale');
                        if (empty($locale)) {
                            $locale = $publication->getData('locale');
                        }
                        $author->setBiography($n->textContent, $locale);
                        break;
                }
            }
        }

        $authorGivenName = $author->getFullName(true, false, $publication->getData('locale'));
        if (empty($authorGivenName)) {
            $deployment->addError(
                Application::ASSOC_TYPE_SUBMISSION,
                $publication->getId(),
                __('plugins.importexport.common.error.missingGivenName', [
                    'authorName' => $author->getLocalizedGivenName(),
                    'localeName' => Locale::getMetadata($publication->getData('locale'))->getDisplayName()
                ])
            );
        }

        // Identify the user group by name
        $userGroupName = $node->getAttribute('user_group_ref');

        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        foreach ($userGroups as $userGroup) {
            if (in_array($userGroupName, $userGroup->getName(null))) {
                // Found a candidate; stash it.
                $author->setUserGroupId($userGroup->getId());
                break;
            }
        }

        if (!$author->getUserGroupId()) {
            $authorFullName = $author->getFullName(true, false, $publication->getData('locale'));
            $deployment->addError(Application::ASSOC_TYPE_AUTHOR, $publication->getId(), __('plugins.importexport.common.error.unknownUserGroup', ['authorName' => $authorFullName, 'userGroupName' => $userGroupName]));
            throw new Exception(__('plugins.importexport.author.exportFailed'));
        }

        $authorId = Repo::author()->add($author);
        $author->setId($authorId);

        $importAuthorId = $node->getAttribute('id');
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
