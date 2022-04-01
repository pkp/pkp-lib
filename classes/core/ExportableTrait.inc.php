<?php

/**
 * @file classes/core/ExportableTrait.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ExportableTrait
 * @ingroup db
 *
 * @brief Implements the __set_state magic method, for classes that have a parameterless constructor, in order to recover classes exported with "var_export"
 */

namespace PKP\core;

trait ExportableTrait
{
    /**
     * Generic __set_state implementation
     */
    public static function __set_state(array $data)
    {
        $object = new static();
        foreach ($data as $key => $value) {
            $object->$key = $value;
        }
        return $object;
    }
}
