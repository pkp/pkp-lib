<?php

/**
 * @file classes/cache/APCCache.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class APCCache
 * @ingroup cache
 *
 * @see GenericCache
 *
 * @brief Provides caching based on APC's variable store.
 */

namespace PKP\cache;

class apc_false
{
};

class APCCache extends GenericCache
{
    /**
     * Flush the cache.
     */
    public function flush()
    {
        $prefix = INDEX_FILE_LOCATION . ':' . $this->getContext() . ':' . $this->getCacheId();
        $info = apc_cache_info('user');
        foreach ($info['cache_list'] as $entry) {
            if (substr($entry['info'], 0, strlen($prefix)) == $prefix) {
                apc_delete($entry['info']);
            }
        }
    }

    /**
     * Get an object from the cache.
     *
     * @param mixed $id
     */
    public function getCache($id)
    {
        $key = INDEX_FILE_LOCATION . ':' . $this->getContext() . ':' . $this->getCacheId() . ':' . $id;
        $returner = unserialize(apc_fetch($key));
        if ($returner === false) {
            return $this->cacheMiss;
        }
        if ($returner instanceof apc_false) {
            $returner = false;
        }
        return $returner;
    }

    /**
     * Set an object in the cache. This function should be overridden
     * by subclasses.
     *
     * @param mixed $id
     * @param mixed $value
     */
    public function setCache($id, $value)
    {
        $key = INDEX_FILE_LOCATION . ':' . $this->getContext() . ':' . $this->getCacheId() . ':' . $id;
        if ($value === false) {
            $value = new apc_false();
        }
        apc_store($key, serialize($value));
    }

    /**
     * Get the time at which the data was cached.
     * Not implemented in this type of cache.
     */
    public function getCacheTime()
    {
        return null;
    }

    /**
     * Set the entire contents of the cache.
     * WARNING: THIS DOES NOT FLUSH THE CACHE FIRST!
     *
     * @param array $contents Complete cache contents.
     */
    public function setEntireCache($contents)
    {
        foreach ($contents as $id => $value) {
            $this->setCache($id, $value);
        }
    }
}
