<?php

/**
 * @file classes/submission/Genre.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Genre
 *
 * @ingroup submission
 *
 * @see GenreDAO
 *
 * @brief Basic class describing a genre.
 */

namespace PKP\submission;

use PKP\db\DAORegistry;

class Genre extends \PKP\core\DataObject
{
    public const GENRE_CATEGORY_DOCUMENT = 1;
    public const GENRE_CATEGORY_ARTWORK = 2;
    public const GENRE_CATEGORY_SUPPLEMENTARY = 3;

    // Genre category-specific metadata fields.
    // Some may not be limited to these categories only but should be considered for the specified genre categories.

    // Artwork
    public const METADATA_ARTWORK_CAPTION = 'artworkCaption';
    public const METADATA_ARTWORK_CREDIT = 'artworkCredit';
    public const METADATA_ARTWORK_COPYRIGHT_OWNER = 'artworkCopyrightOwner';
    public const METADATA_ARTWORK_PERMISSION_TERMS = 'artworkPermissionTerms';

    // Supplementary
    public const METADATA_SUPPLEMENTARY_DESCRIPTION = 'description';
    public const METADATA_SUPPLEMENTARY_CREATOR = 'creator';
    public const METADATA_SUPPLEMENTARY_PUBLISHER = 'publisher';
    public const METADATA_SUPPLEMENTARY_SOURCE = 'source';
    public const METADATA_SUPPLEMENTARY_SUBJECT = 'subject';
    public const METADATA_SUPPLEMENTARY_SPONSOR = 'sponsor';
    public const METADATA_SUPPLEMENTARY_DATE_CREATED = 'dateCreated';
    public const METADATA_SUPPLEMENTARY_LANGUAGE = 'language';


    /**
     * Get ID of context.
     */
    public function getContextId(): int
    {
        return $this->getData('contextId');
    }

    /**
     * Set ID of context.
     */
    public function setContextId(int $contextId): void
    {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get sequence of genre.
     *
     * @return float
     */
    public function getSequence()
    {
        return $this->getData('sequence');
    }

    /**
     * Set sequence of genre.
     *
     * @param float $sequence
     */
    public function setSequence($sequence)
    {
        $this->setData('sequence', $sequence);
    }

    /**
     * Get key of genre.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->getData('key');
    }

    /**
     * Set key of genre.
     *
     * @param string $key
     */
    public function setKey($key)
    {
        $this->setData('key', $key);
    }

    /**
     * Get enabled status of genre.
     *
     * @return bool
     */
    public function getEnabled()
    {
        return $this->getData('enabled');
    }

    /**
     * Set enabled status of genre.
     *
     * @param bool $enabled
     */
    public function setEnabled($enabled)
    {
        $this->setData('enabled', $enabled);
    }

    /**
     * Set the name of the genre
     *
     * @param string $name
     * @param string $locale
     */
    public function setName($name, $locale)
    {
        $this->setData('name', $name, $locale);
    }

    /**
     * Get the name of the genre
     *
     * @param string $locale
     *
     * @return string
     */
    public function getName($locale)
    {
        return $this->getData('name', $locale);
    }

    /**
     * Get the localized name of the genre
     *
     * @return string
     */
    public function getLocalizedName()
    {
        return $this->getLocalizedData('name');
    }

    /**
     * Get context file category (e.g. artwork or document)
     *
     * @return int GENRE_CATEGORY_...
     */
    public function getCategory()
    {
        return $this->getData('category');
    }

    /**
     * Set context file category (e.g. artwork or document)
     *
     * @param int $category GENRE_CATEGORY_...
     */
    public function setCategory($category)
    {
        $this->setData('category', $category);
    }

    /**
     * Get dependent flag
     *
     * @return bool
     */
    public function getDependent()
    {
        return $this->getData('dependent');
    }

    /**
     * Set dependent flag
     *
     * @param bool $dependent
     */
    public function setDependent($dependent)
    {
        $this->setData('dependent', $dependent);
    }

    /**
     * Get supplementary flag
     *
     * @return bool
     */
    public function getSupplementary()
    {
        return $this->getData('supplementary');
    }

    /**
     * Set supplementary flag
     *
     * @param bool $supplementary
     */
    public function setSupplementary($supplementary)
    {
        $this->setData('supplementary', $supplementary);
    }

    /**
     * Get whether this file is required for new submissions
     */
    public function getRequired(): bool
    {
        return (bool) $this->getData('required');
    }

    /**
     * Set whether this file is required for new submissions
     */
    public function setRequired(bool $required): void
    {
        $this->setData('required', $required);
    }

    /**
     * Get whether this file supports media variant types
     */
    public function getSupportsFileVariants(): bool
    {
        return (bool) $this->getData('supportsFileVariants');
    }

    /**
     * Set whether this file supports media variant types
     */
    public function setSupportsFileVariants(bool $supportsFileVariants): void
    {
        $this->setData('supportsFileVariants', $supportsFileVariants);
    }

    /**
     * Is this a default genre.
     *
     * @return bool
     */
    public function isDefault()
    {
        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        $defaultKeys = $genreDao->getDefaultKeys();
        return in_array($this->getKey(), $defaultKeys);
    }

    /**
     * Get category-specific metadata fields for genre categories
     *
     * @return array<int,list<string>> Keyed by GENRE_CATEGORY_* constant, values are lists of METADATA_* field name strings
     */
    public static function getCategoryMetadataFields(): array
    {
        return [
            self::GENRE_CATEGORY_DOCUMENT => [],
            self::GENRE_CATEGORY_ARTWORK => [
                self::METADATA_ARTWORK_CAPTION,
                self::METADATA_ARTWORK_CREDIT,
                self::METADATA_ARTWORK_COPYRIGHT_OWNER,
                self::METADATA_ARTWORK_PERMISSION_TERMS,
            ],
            self::GENRE_CATEGORY_SUPPLEMENTARY => [
                self::METADATA_SUPPLEMENTARY_DESCRIPTION,
                self::METADATA_SUPPLEMENTARY_CREATOR,
                self::METADATA_SUPPLEMENTARY_PUBLISHER,
                self::METADATA_SUPPLEMENTARY_SOURCE,
                self::METADATA_SUPPLEMENTARY_SUBJECT,
                self::METADATA_SUPPLEMENTARY_SPONSOR,
                self::METADATA_SUPPLEMENTARY_DATE_CREATED,
                self::METADATA_SUPPLEMENTARY_LANGUAGE,
            ],
        ];
    }
}
