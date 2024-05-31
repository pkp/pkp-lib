<?php

/**
 * @file classes/invitation/core/PKPInvitationDiscovery.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInvitationDiscovery
 *
 * @ingroup invitation
 *
 * @brief Invitation Discovery service class
 */

namespace PKP\invitation\core;

use PKP\invitation\invitations\Invitation;
use ReflectionClass;

class PKPInvitationDiscovery 
{
    public static function discoverInvitations(): array
    {
        $invitations = [];

        $invitationClasses = array_filter(get_declared_classes(), function($value) {
            return preg_match('/Invite$/', $value) === 1;
        });

        foreach ($invitationClasses as $invitationClass) {
            if (is_subclass_of($invitationClass, Invitation::class)) {
                $reflectedClass = new ReflectionClass($invitationClass);
                if (!$reflectedClass->isAbstract()) {
                    $type = $invitationClass::getType();
                    $invitations[$type] = $invitationClass;
                }
            }
        }

        return $invitations;
    }
}