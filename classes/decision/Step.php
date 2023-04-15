<?php
/**
 * @file classes/decision/Step.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Step
 *
 * @brief A base class to define a step in an editorial decision workflow
 */

namespace PKP\decision;

use Exception;
use stdClass;

abstract class Step
{
    public string $id;
    public string $type;
    public string $name;
    public string $description;

    /**
     * @param string $id A unique id for this step
     * @param string $name The name of this step. Shown to the user.
     * @param string $description A description of this step. Shown to the user.
     */
    public function __construct(string $id, string $name, string $description = '')
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        if (!isset($this->type)) {
            throw new Exception('Decision workflow step created without specifying a type.');
        }
    }

    /**
     * Compile initial state data to pass to the frontend
     */
    public function getState(): stdClass
    {
        $config = new stdClass();
        $config->id = $this->id;
        $config->type = $this->type;
        $config->name = $this->name;
        $config->description = $this->description;
        $config->errors = new stdClass();

        return $config;
    }
}
