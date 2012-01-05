<?php

/**
 * @file classes/codelist/ONIXCodelistItemDAO.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ONIXCodelistItemDAO.inc.php
 * @ingroup codelist
 * @see CodelistItem
 *
 * @brief Parent class for operations involving Codelist objects.
 *
 */

import('lib.pkp.classes.codelist.ONIXCodelistItem');

class ONIXCodelistItemDAO extends DAO {

	/* The name of the codelist we are interested in */
	var $_list;

	/**
	 * Constructor.
	 */
	function ONIXCodelistItemDAO() {
		parent::DAO();
	}

	function &_getCache($locale = null) {
		if ($locale == null) {
			$locale = AppLocale::getLocale();
		}

		$cacheName = 'Onix' . $this->getListName() . 'Cache';

		$cache =& Registry::get($cacheName, true, null);
		if ($cache === null) {
			$cacheManager = CacheManager::getManager();
			$cache =& $cacheManager->getFileCache(
				$this->getListName() . '_codelistItems', $locale,
				array(&$this, '_cacheMiss')
			);
			$cacheTime = $cache->getCacheTime();
			if ($cacheTime !== null && $cacheTime < filemtime($this->getFilename($locale))) {
				$cache->flush();
			}
		}

		return $cache;
	}

	function _cacheMiss(&$cache, $id) {
		$allCodelistItems =& Registry::get('all' . $this->getListName() . 'CodelistItems', true, null);
		if ($allCodelistItems === null) {
			// Add a locale load to the debug notes.
			$notes =& Registry::get('system.debug.notes');
			$locale = $cache->cacheId;
			if ($locale == null) {
				$locale = AppLocale::getLocale();
			}
			$filename = $this->getFilename($locale);
			$notes[] = array('debug.notes.codelistItemListLoad', array('filename' => $filename));

			// Reload locale registry file
			$xmlDao = new XMLDAO();
			$listName =& $this->getListName(); // i.e., 'List30'
			import('lib.pkp.classes.codelist.ONIXParserDOMHandler');
			$handler = new ONIXParserDOMHandler($listName);
			$data = $xmlDao->parseWithHandler($filename, $handler);
			// Build array with ($charKey => array(stuff))

			if (isset($data[$listName])) {
				foreach ($data[$listName] as $code => $codelistData) {
					$allCodelistItems[$code] = $codelistData;
				}
			}
			if (is_array($allCodelistItems)) {
				asort($allCodelistItems);
			}

			$cache->setEntireCache($allCodelistItems);

		}
		return null;
	}

	function getFilename($locale) {
		if (!AppLocale::isLocaleValid($locale)) {
			$locale = AppLocale::MASTER_LOCALE;
		}
		return "lib/pkp/locale/$locale/ONIX_BookProduct_CodeLists.xsd";
	}

	/**
	 * Set the name of the list we want.
	 * @param $list string
	 */
	function setListName($list) {
		$this->_list =& $list;
	}
	/**
	 * Get the base node name particular codelist database.
	 * @return string
	 */
	function getListName() {
		return $this->_list;
	}

	/**
	 * Get the name of the CodelistItem subclass.
	 * @return String
	 */
	function newDataObject() {
		return new ONIXCodelistItem();
	}

	/**
	 * Retrieve an array of all the codelist items.
	 * @param $list the List string for this code list (i.e., List30)
	 * @param $locale an optional locale to use
	 * @return array of CodelistItems
	 */
	function &getCodelistItems($list, $locale = null) {
		$this->setListName($list);
		$cache =& $this->_getCache($locale);
		$returner = array();
		foreach ($cache->getContents() as $code => $entry) {
			$returner[] =& $this->_returnFromRow($code, $entry);
		}
		return $returner;
	}

	/**
	 * Retrieve an array of all codelist codes and values for a given list.
	 * @param $list the List string for this code list (i.e., List30)
	 * @param $codesToExclude an optional list of codes to exclude from the returned list
	 * @param $locale an optional locale to use
	 * @return array of CodelistItem names
	 */
	function &getCodes($list, $codesToExclude = array(), $locale = null) {
		$this->setListName($list);
		$cache =& $this->_getCache($locale);
		$returner = array();
		$cacheContents =& $cache->getContents();
		if (is_array($cacheContents)) {
			foreach ($cache->getContents() as $code => $entry) {
				if ($code != '') {
					if (!in_array($code, $codesToExclude))
						$returner[$code] =& $entry[0];
				}
			}
		}
		return $returner;
	}

	/**
	 * Internal function to return a Codelist object from a row.
	 * @param $row array
	 * @return CodelistItem
	 */
	function &_returnFromRow($code, &$entry) {
		$codelistItem = $this->newDataObject();
		$codelistItem->setCode($code);
		$codelistItem->setText($entry[0]);

		HookRegistry::call('ONIXCodelistItemDAO::_returnFromRow', array(&$codelistItem, &$code, &$entry));

		return $codelistItem;
	}
}
?>
