<?php

/**
 * @defgroup cache Cache
 * Implements various forms of caching, i.e. object caches, file caches, etc.
 */

/**
 * @file classes/cache/FileCache.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileCache
 * @ingroup cache
 *
 * @brief Provides caching based on machine-generated PHP code on the filesystem.
 */

namespace PKP\cache;

use Exception;
use PKP\config\Config;
use PKP\file\FileManager;

class FileCache extends GenericCache
{
    /**
     * Connection to use for caching.
     */
    public $filename;

    /**
     * The cached data
     */
    public $cache;

    /**
     * Instantiate a cache.
     */
    public function __construct($context, $cacheId, $fallback, $path)
    {
        parent::__construct($context, $cacheId, $fallback);

        $this->filename = "{$path}/fc-{$context}-" . str_replace('/', '.', $cacheId) . '.php';

        // If the file couldn't be opened or if a lock couldn't be acquired, quit
        if (!($fp = @fopen($this->filename, 'r')) || !flock($fp, LOCK_SH)) {
            return $this->cache = null;
        }

        // Reasoning: When the include below fails, it returns "false" and we have no way to determine if it's an error or a valid cache value
        set_error_handler(static fn () => throw new Exception('Failed to include file'));
        try {
            $this->cache = include $this->filename;
        } catch (Exception) {
            $this->cache = null;
        } finally {
            restore_error_handler();
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Flush the cache
     */
    public function flush()
    {
        unset($this->cache);
        $this->cache = null;
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->filename, true);
        }
        @unlink($this->filename);
    }

    /**
     * Get an object from the cache.
     *
     * @param string $id
     */
    public function getCache($id)
    {
        if (!isset($this->cache)) {
            return $this->cacheMiss;
        }
        return ($this->cache[$id] ?? null);
    }

    /**
     * Set an object in the cache. This function should be overridden
     * by subclasses.
     *
     * @param string $id
     */
    public function setCache($id, $value)
    {
        // Flush the cache; it will be regenerated on demand.
        $this->flush();
    }

    /**
     * Set the entire contents of the cache.
     */
    public function setEntireCache($contents)
    {
        if (@file_put_contents(
            $this->filename,
            '<?php return ' . var_export($contents, true) . ';',
            LOCK_EX
        ) !== false) {
            $umask = Config::getVar('files', 'umask');
            if ($umask) {
                @chmod($this->filename, FileManager::FILE_MODE_MASK & ~$umask);
            }
        }
        $this->cache = $contents;
    }

    /**
     * Get the time at which the data was cached.
     * If the file does not exist or an error occurs, null is returned.
     *
     * @return int|null
     */
    public function getCacheTime()
    {
        $result = @filemtime($this->filename);
        if ($result === false) {
            return null;
        }
        return ((int) $result);
    }

    /**
     * Get the entire contents of the cache in an associative array.
     * @return mixed
     */
    public function &getContents()
    {
        if (!isset($this->cache)) {
            // Trigger a cache miss to load the cache.
            $this->get(null);
        }
        return $this->cache;
    }
}
