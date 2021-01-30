<?php

/**
 * @file classes/publication/PKPPublication.inc.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublication
 * @ingroup publication
 * @see PublicationDAO
 *
 * @brief Base class for Publication.
 */

class PKPPublication extends DataObject {

	/**
	 * Get localized data for this object.
	 *
	 * It selects the locale in the following order:
	 * - $preferredLocale
	 * - the user's current locale
	 * - the publication's primary locale
	 * - the first locale we find data for
	 *
	 * @param $key string
	 * @param $preferredLocale string
	 * @param $selectedLocale Optional reference to receive locale used for return value.
	 * @return mixed
	 */
	public function getLocalizedData($key, $preferredLocale = null, &$selectedLocale = null) {
		// 1. Preferred locale
		if ($preferredLocale && $this->getData($key, $preferredLocale)) {
			$selectedLocale = $preferredLocale;
			return $this->getData($key, $preferredLocale);
		}
		// 2. User's current locale
		if (!empty($this->getData($key, AppLocale::getLocale()))) {
			$selectedLocale = AppLocale::getLocale();
			return $this->getData($key, AppLocale::getLocale());
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
	 * @param string $preferredLocale Override the publication's default
	 *  locale and return the title in a specified locale.
	 * @return string
	 */
	public function getLocalizedFullTitle($preferredLocale = null) {
		$fullTitle = $this->getLocalizedTitle($preferredLocale);
		$subtitle = $this->getLocalizedData('subtitle', $preferredLocale);
		if ($subtitle) {
			return PKPString::concatTitleFields([$fullTitle, $subtitle]);
		}
		return $fullTitle;
	}

	/**
	 * Return the combined prefix, title and subtitle for all locales
	 *
	 * @return array
	 */
	public function getFullTitles() {
		$allTitles = (array) $this->getData('title');
		$return = [];
		foreach ($allTitles as $locale => $title) {
			if (!$title) {
				continue;
			}
			$return[$locale] = $this->getLocalizedFullTitle($locale);
		}
		return $return;
	}

	/**
	 * Combine the localized title and prefix
	 *
	 * @param string $preferredLocale Override the publication's default
	 *  locale and return the title in a specified locale.
	 * @return string
	 */
	public function getLocalizedTitle($preferredLocale = null) {
		$usedLocale = null;
		$title = $this->getLocalizedData('title', $preferredLocale, $usedLocale);
		$prefix = $this->getData('prefix', $usedLocale);
		if ($prefix) {
			return $prefix . ' ' . $title;
		}
		return $title;
	}

	/**
	 * Return the combined title and prefix for all locales
	 *
	 * @return array
	 */
	public function getTitles() {
		$allTitles = $this->getData('title');
		$return = [];
		foreach ($allTitles as $locale => $title) {
			if (!$title) {
				continue;
			}
			$return[] = $this->getLocalizedTitle($locale);
		}
		return $return;
	}

	/**
	 * Combine author names and roles into a string
	 *
	 * Eg - Daniel Barnes, Carlo Corino (Author); Alan Mwandenga (Translator)
	 *
	 * @param array $userGroups List of UserGroup objects
	 * @return string
	 */
	public function getAuthorString($userGroups) {
		$authors = $this->getData('authors');

		if (empty($authors)) {
			return '';
		}

		$str = '';
		$lastUserGroupId = null;
		foreach($authors as $author) {
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
			foreach($userGroups as $userGroup) {
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
	 * @return string
	 */
	public function getShortAuthorString() {
		$authors = $this->getData('authors');

		if (empty($authors)) {
			return '';
		}

		$str = $authors[0]->getLocalizedFamilyName();
		if (!$str) {
			$str = $authors[0]->getLocalizedGivenName();
		}

		if (count($authors) > 1) {
			AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);
			return __('submission.shortAuthor', ['author' => $str]);
		}

		return $str;
	}

	/**
	 * Get the primary contact
	 *
	 * @return Author|null
	 */
	public function getPrimaryAuthor() {
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
	public function stampModified() {
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
	public function getStartingPage() {
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
	public function getEndingPage() {
		$ranges = $this->getPageArray();
		$lastRange = array_pop($ranges);
		$lastPage = is_array($lastRange) ? array_pop($lastRange) : "";
		return isset($lastPage) ? $lastPage : "";
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
	public function getPageArray() {
		$pages = $this->getData('pages');
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
			return array();
		}
		// commas indicate distinct ranges
		$ranges = explode(',', $pages);
		$pageArray = array();
		foreach ($ranges as $range) {
			// hyphens (or double-hyphens) indicate range spans
			$pageArray[] = array_map('trim', explode('-', str_replace('--', '-', $range), 2));
		}
		return $pageArray;
	}

	/**
	 * Is the license for copyright on this publication a Creative Commons license?
	 *
	 * @return boolean
	 */
	function isCCLicense() {
		return preg_match('/creativecommons\.org/i', $this->getData('licenseUrl'));
	}

	/**
	 * Get stored public ID of the publication
	 *
	 * This helper function is required by PKPPubIdPlugins.
	 * @see Submission::getStoredPubId()
	 */
	function getStoredPubId($pubIdType) {
		return $this->getData('pub-id::' . $pubIdType);
	}
}


