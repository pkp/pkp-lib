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
     * The allowed composed email properties.
     * Invitations carry an access key, so they must not be copied to other addresses.
     */
    protected const COMPOSED_EMAIL_PROPERTIES = [
        'emailComposer' => ['subject', 'body'],
    ];

    /**
     * The base constructor for the payload class.
     * It accepts an associative array to initialize properties.
     */
    public function __construct(array $attributes = [])
    {
        $attributes = static::sanitizeComposedEmails($attributes);

        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Remove the keys that are not kept from the composed emails. e.g. cc and bcc
     */
    protected static function sanitizeComposedEmails(array $attributes): array
    {
        foreach (static::COMPOSED_EMAIL_PROPERTIES as $property => $allowedKeys) {
            if (is_array($attributes[$property] ?? null)) {
                $attributes[$property] = Arr::only($attributes[$property], $allowedKeys);
            }
        }

        return $attributes;
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
