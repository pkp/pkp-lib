<?php
/**
 * @file classes/core/maps/Base.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class schema
 *
 * @brief A base class for creating extensible maps of any kind.
 */

namespace PKP\core\maps;

abstract class Base
{
    /** @var array Callbacks that should be applied to each object in the map. */
    protected $extensions = [];

    /**
     * Extend the map with a custom callback function
     *
     * Example:
     *
     * $map->extend(function($output, $input, $map) {
     *   $output['example'] = $intput->example;
     *   return $output;
     * })
     *
     */
    public function extend(callable $cb): self
    {
        $this->extensions[] = $cb;
        return $this;
    }

    /**
     * Run extensions applied to this map
     *
     * Run the callback functions registered with extend.
     *
     * @param mixed $output The output the object is being mapped to
     * @param mixed $input The object that is being mapped from
     */
    protected function withExtensions($output, $input)
    {
        foreach ($this->extensions as $extension) {
            $output = call_user_func($extension, $output, $input, $this);
        }
        return $output;
    }
}
