<?php

/**
 * @file classes/core/MapsContainer.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MapContainer
 * @ingroup core
 *
 * @brief This service provider allows Map classes to be instantiated and
 *  applies any extensions to the map registered by plugins.
 */

namespace PKP\core;

use PKP\core\maps\Base;

class MapContainer
{
    /** @var array Key/value map that stores extensions to maps registered by plugins */
    protected array $extensions = [];

    public function extend(string $map, callable $callback)
    {
        if (isset($this->extensions[$map])) {
            $this->extensions[$map][] = $callback;
        }
        $this->extensions[$map] = [$callback];
    }

    public function getMap(string $class, array $dependencies = []): Base
    {
        return app($class, $dependencies);
    }

    public function withExtensions(string $class, array $dependencies = []): Base
    {
        $map = $this->getMap($class, $dependencies);
        foreach ($this->extensions as $name => $extensions) {
            if (is_a($map, $name)) {
                foreach ($extensions as $extension) {
                    $map->extend($extension);
                }
            }
        }
        return $map;
    }
}
