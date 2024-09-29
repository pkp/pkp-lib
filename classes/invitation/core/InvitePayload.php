<?php

/**
 * @file classes/invitation/invitations/payload/UserRoleAssignmentInvitePayload.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentInvitePayload
 *
 * @brief Payload for the assign Roles to User invitation
 */

namespace PKP\invitation\core;

use Illuminate\Support\Arr;

abstract class InvitePayload
{
    /**
     * The base constructor for the payload class.
     * It accepts an associative array to initialize properties.
     */
    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Create an instance of the Payload from an array.
     */
    public static function fromArray(array $data): static
    {
        $className = get_called_class();
        $classVars = get_class_vars($className);

        $filteredData = array_merge($classVars, Arr::only($data, array_keys($classVars)));

        // Instantiate the subclass with the array, letting the constructor handle the details
        return new $className(...$filteredData);
    }

    /**
     * Convert the Payload instance to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
