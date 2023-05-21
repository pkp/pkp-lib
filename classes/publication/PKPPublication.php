<?php

/**
 * @file classes/publication/PKPPublication.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublication
 *
 * @ingroup publication
 *
 * @see DAO
 *
 * @brief Base class for Publication.
 */

namespace PKP\publication;

use APP\author\Author;
use APP\facades\Repo;
use PKP\core\Core;
use PKP\core\PKPString;
use PKP\facades\Locale;
use PKP\userGroup\UserGroup;

class PKPPublication extends \PKP\core\DataObject
{
    /**
     * Get localized data for this object.
     *
     * It selects the locale in the following order:
     * - $preferredLocale
     * - the user's current locale
     * - the publication's primary locale
     * - the first locale we find data for
     *
     * @param string $key
     * @param string $preferredLocale
     * @param string $selectedLocale Optional reference to receive locale used for return value.
     */
    public function getLocalizedData($key, $preferredLocale = null, &$selectedLocale = null)
    {
        // 1. Preferred locale
        if ($preferredLocale && $this->getData($key, $preferredLocale)) {
            $selectedLocale = $preferredLocale;
            return $this->getData($key, $preferredLocale);
        }
        // 2. User's current locale
        if (!empty($this->getData($key, Locale::getLocale()))) {
            $selectedLocale = Locale::getLocale();
            return $this->getData($key, Locale::getLocale());
        }
        // 3. Publication's primary locale
        if (!empty($this->getData($key, $this->getData('locale')))) {
            $selectedLocale = $this->getData('locale');
            return $this->getData($key, $this->getData('locale'));
        }
        // 4. The first locale we can find data for
        $data = $this->getData($key, null);
        foreach ((array) $data as $locale => $value) {
            if (!empty($value)) {
                $selectedLocale = $locale;
                return $value;
            }
        }

        return null;
    }

    /**
     * Combine the localized title, prefix and subtitle
     *
     * @param  string $preferredLocale  Override the publication's default locale and return the title in a specified locale.
     * @param  string $format           Define the return data format as text or html
     *
     * @return string
     */
    public function getLocalizedFullTitle($preferredLocale = null, string $format = 'text')
    {
        $fullTitle = $this->getLocalizedTitle($preferredLocale, $format);
        $subtitle = $this->getLocalizedSubTitle($preferredLocale, $format);

        if ($subtitle) {
            return PKPString::concatTitleFields([$fullTitle, $subtitle]);
        }

        return $fullTitle;
    }

    /**
     * Return the combined prefix, title and subtitle for all locales
     *
     * @param  string $format Define the return data format as text or html
     *
     * @return array
     */
    public function getFullTitles(string $format = 'text')
    {
        $allTitles = (array) $this->getData('title');
        $return = [];
        foreach ($allTitles as $locale => $title) {
            if (!$title) {
                continue;
            }
            $return[$locale] = $this->getLocalizedFullTitle($locale, $format);
        }
        return $return;
    }

    /**
     * Combine the localized title and prefix
     *
     * @param  string $preferredLocale  Override the publication's default locale and return the title in a specified locale.
     * @param  string $format           Define the return data format as text or html
     *
     * @return string
     */
    public function getLocalizedTitle($preferredLocale = null, string $format = 'text')
    {
        $usedLocale = null;
        $title = $this->getLocalizedData('title', $preferredLocale, $usedLocale);
        $prefix = $this->getData('prefix', $usedLocale);

        switch (strtolower($format)) {
            case 'html':
                // Title is already in HTML, prefix is in text. Convert prefix.
                if ($prefix) {
                    $prefix = htmlspecialchars($prefix);
                }
                break;
            case 'text':
                // Title is in HTML, prefix is already in text. Convert title.
                $title = strip_tags($title);
                break;
            default: throw new \Exception('Invalid format!');
        }

        if ($prefix) {
            $title = $prefix . ' ' . $title;
        }

        return $title;
    }

    /**
     * Get the localized sub title
     *
     * @param  string $preferredLocale  Override the publication's default locale and return the title in a specified locale.
     * @param  string $format           Define the return data format as text or html
     *
     * @return string
     */
    public function getLocalizedSubTitle($preferredLocale = null, string $format = 'text')
    {
        $subTitle = $this->getLocalizedData('subtitle', $preferredLocale);

        if ($subTitle) {
            return strtolower($format) === 'text' ? strip_tags($subTitle) : $subTitle;
        }

        return '';
    }

    /**
     * Return the combined title and prefix for all locales
     *
     * @param  string $format Define the return data format as text or html
     *
     * @return array
     */
    public function getTitles(string $format = 'text')
    {
        $allTitles = $this->getData('title');
        $return = [];
        foreach ($allTitles as $locale => $title) {
            if (!$title) {
                continue;
            }
            $return[$locale] = $this->getLocalizedTitle($locale, $format);
        }
        return $return;
    }

    /**
     * Return all the sub titles
     *
     * @param  string $format Define the return data format as text or html
     *
     * @return array
     */
    public function getSubTitles(string $format = 'text')
    {
        $allSubTitles = $this->getData('subtitle');
        $return = [];
        foreach ($allSubTitles ?? [] as $locale => $subTitle) {
            if (!$subTitle) {
                continue;
            }
            $return[$locale] = $this->getLocalizedSubTitle($locale, $format);
        }
        return $return;
    }

    /**
     * Combine author names and roles into a string
     *
     * Eg - Daniel Barnes, Carlo Corino (Author); Alan Mwandenga (Translator)
     *
     * @param \Traversable<UserGroup> $userGroups List of UserGroup objects
     * @param bool $includeInBrowseOnly true if only the includeInBrowse Authors will be contained
     *
     * @return string
     */
    public function getAuthorString(\Traversable $userGroups, $includeInBrowseOnly = false)
    {
        $authors = $this->getData('authors');

        if (empty($authors)) {
            return '';
        }

        if ($includeInBrowseOnly) {
            $authors = $authors->filter(function ($author, $key) {
                return $author->getData('includeInBrowse');
            });
        }

        $str = '';
        $lastUserGroupId = null;
        foreach ($authors as $author) {
            if (!empty($str)) {
                if ($lastUserGroupId != $author->getData('userGroupId')) {
                    foreach ($userGroups as $userGroup) {
                        if ($lastUserGroupId === $userGroup->getId()) {
                            if ($userGroup->getData('showTitle')) {
                                $str .= ' (' . $userGroup->getLocalizedData('name') . ')';
                            }
                            break;
                        }
                    }
                    $str .= __('common.semicolonListSeparator');
                } else {
                    $str .= __('common.commaListSeparator');
                }
            }
            $str .= $author->getFullName();
            $lastUserGroupId = $author->getUserGroupId();
        }

        // If there needs to be a trailing user group title, add it
        if (isset($author)) {
            foreach ($userGroups as $userGroup) {
                if ($author->getData('userGroupId') === $userGroup->getId()) {
                    if ($userGroup->getData('showTitle')) {
                        $str .= ' (' . $userGroup->getLocalizedData('name') . ')';
                    }
                    break;
                }
            }
        }

        return $str;
    }

    /**
     * Combine the author names into a shortened string
     *
     * Eg - Barnes, et al.
     *
     * @param string|null $defaultLocale
     *
     * @return string
     */
    public function getShortAuthorString($defaultLocale = null)
    {
        $authors = $this->getData('authors');

        if (!$authors->count()) {
            return '';
        }

        $firstAuthor = $authors->first();

        $str = $firstAuthor->getLocalizedData('familyName', $defaultLocale);
        if (!$str) {
            $str = $firstAuthor->getLocalizedData('givenName', $defaultLocale);
        }

        if ($authors->count() > 1) {
            return __('submission.shortAuthor', ['author' => $str], $defaultLocale);
        }

        return $str;
    }

    /**
     * Get the primary contact
     *
     * @return Author|null
     */
    public function getPrimaryAuthor()
    {
        if (empty($this->getData('authors'))) {
            return null;
        }
        foreach ($this->getData('authors') as $author) {
            if ($author->getId() === $this->getData('primaryContactId')) {
                return $author;
            }
        }
    }

    /**
     * Stamp the date of the last modification to the current time.
     */
    public function stampModified()
    {
        return $this->setData('lastModified', Core::getCurrentDate());
    }

    /**
     * Get the starting page of this publication
     *
     * Note the return type of string - this is not to be used for
     * page counting.
     *
     * @return string
     */
    public function getStartingPage()
    {
        $ranges = $this->getPageArray();
        $firstRange = array_shift($ranges);
        if (is_array($firstRange)) {
            return array_shift($firstRange);
        }
        return '';
    }

    /**
     * Get ending page of a this publication
     *
     * Note the return type of string - this is not to be used for
     * page counting.
     *
     * @return string
     */
    public function getEndingPage()
    {
        $ranges = $this->getPageArray();
        $lastRange = array_pop($ranges);
        $lastPage = is_array($lastRange) ? array_pop($lastRange) : '';
        return $lastPage ?? '';
    }

    /**
     * Get pages converted to a nested array of page ranges
     *
     * For example, pages of "pp. ii-ix, 9,15-18,a2,b2-b6" will return:
     *
     * [
     *  ['ii', 'ix'],
     *  ['9'],
     *  ['15', '18'],
     *  ['a2'],
     *  ['b2', 'b6'],
     * ]
     *
     * @return array
     */
    public function getPageArray()
    {
        $pages = $this->getData('pages') ?? '';
        // Strip any leading word
        if (preg_match('/^[[:alpha:]]+\W/', $pages)) {
            // but don't strip a leading roman numeral
            if (!preg_match('/^[MDCLXVUI]+\W/i', $pages)) {
                // strip the word or abbreviation, including the period or colon
                $pages = preg_replace('/^[[:alpha:]]+[:.]?/', '', $pages);
            }
        }
        // strip leading and trailing space
        $pages = trim($pages);
        // shortcut the explode/foreach if the remainder is an empty value
        if ($pages === '') {
            return [];
        }
        // commas indicate distinct ranges
        $ranges = explode(',', $pages);
        $pageArray = [];
        foreach ($ranges as $range) {
            // hyphens (or double-hyphens) indicate range spans
            $pageArray[] = array_map('trim', explode('-', str_replace(['--', '–'], '-', $range), 2));
        }
        return $pageArray;
    }

    /**
     * Is the license for copyright on this publication a Creative Commons license?
     *
     * @return bool
     */
    public function isCCLicense()
    {
        return preg_match('/creativecommons\.org/i', $this->getData('licenseUrl'));
    }

    /**
     * Helper method to fetch current DOI
     *
     */
    public function getDoi(): ?string
    {
        $doiObject = $this->getData('doiObject');

        if (empty($doiObject)) {
            return null;
        } else {
            return $doiObject->getData('doi');
        }
    }

    /**
     * Get stored public ID of the publication
     *
     * This helper function is required by PKPPubIdPlugins.
     * NB: To maintain backwards compatability, getDoi() is called from here
     *
     * @see Submission::getStoredPubId()
     */
    public function getStoredPubId($pubIdType)
    {
        if ($pubIdType === 'doi') {
            return $this->getDoi();
        } else {
            return $this->getData('pub-id::' . $pubIdType);
        }
    }

    /**
     * Set stored public issue id.
     *
     * @param string $pubIdType One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
     * @param string $pubId
     */
    public function setStoredPubId($pubIdType, $pubId)
    {
        if ($pubIdType == 'doi') {
            if ($doiObject = $this->getData('doiObject')) {
                Repo::doi()->edit($doiObject, ['doi' => $pubId]);
            } else {
                $newDoiObject = Repo::doi()->newDataObject(
                    [
                        'doi' => $pubId,
                        'contextId' => Repo::submission()->get($this->getData('submissionId'))->getData('contextId')
                    ]
                );
                $doiId = Repo::doi()->add($newDoiObject);

                $this->setData('doiId', $doiId);
            }
        } else {
            $this->setData('pub-id::' . $pubIdType, $pubId);
        }
    }
}
if (!PKP_STRICT_MODE) {
    class_alias('\PKP\publication\PKPPublication', '\PKPPublication');
}
