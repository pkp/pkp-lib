<?php

/**
 * @file classes/cache/CacheManager.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup cache
 * @see GenericCache
 *
 * @brief Provides cache management functions.
 *
 */

// $Id$


class CacheManager {
	/**
	 * Get the static instance of the cache manager.
	 * @return object CacheManager
	 */
	function &getManager() {
		$manager =& Registry::get('cacheManager', true, null);
		if ($manager === null) {
			$manager = new CacheManager();
		}
		return $manager;
	}

	/**
	 * Get a file cache.
	 * @param $context string
	 * @param $cacheId string
	 * @param $fallback callback
	 * @return object FileCache
	 */
	function &getFileCache($context, $cacheId, $fallback) {
		import('cache.FileCache');
		$returner = new FileCache(
			$context, $cacheId, $fallback,
			$this->getFileCachePath()
		);
		return $returner;
	}

	/**
	 * Get a cache.
	 * @param $context string
	 * @param $cacheId string
	 * @param $fallback callback
	 * @return object Cache
	 */
	function &getCache($context, $cacheId, $fallback) {
		$cacheType = Config::getVar('cache','cache');
		switch ($cacheType) {
			case 'memcache':
				import('cache.MemcacheCache');
				$cache = new MemcacheCache(
					$context, $cacheId, $fallback,
					Config::getVar('cache','memcache_hostname'),
					Config::getVar('cache','memcache_port')
				);
				break;
			case '': // Provide a default if not specified
			case 'file':
				$cache =& $this->getFileCache($context, $cacheId, $fallback);
				break;
			case 'none':
				import('cache.GenericCache');
				$cache = new GenericCache(
					$context, $cacheId, $fallback
				);
				break;
			default:
				die ("Unknown cache type \"$cacheType\"!\n");
				break;
		}
		return $cache;
	}

	/**
	 * Get the path in which file caches will be stored.
	 * @return string The full path to the file cache directory
	 */
	function getFileCachePath() {
		return Core::getBaseDir() . DIRECTORY_SEPARATOR . 'cache';
	}

	/**
	 * Flush an entire context, if specified, or
	 * the whole cache.
	 * @param $context string The context to flush, if only one is to be flushed
	 */
	function flush($context = null) {
		$cacheType = Config::getVar('cache','cache');
		switch ($cacheType) {
			case 'memcache':
				// There is no(t yet) selective flushing in memcache;
				// invalidate the whole thing.
				$junkCache =& $this->getCache(null, null, null);
				$junkCache->flush();
				break;
			case 'file':
				$filePath = $this->getFileCachePath();
				$files = glob($filePath . DIRECTORY_SEPARATOR . 'fc-' . (isset($context)?$context . '-':'') . '*.php');
				foreach ($files as $file) {
					unlink ($file);
				}
				break;
			case 'none':
				// Nothing necessary.
				break;
			default:
				die ("Unknown cache type \"$cacheType\"!\n");
		}
	}
}

?>
