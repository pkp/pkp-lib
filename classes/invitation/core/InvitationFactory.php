<?php

/**
 * @file invitation/core/InvitationFactory.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationFactory
 *
 * @brief Invitation Factory
 */

namespace PKP\invitation\core;

use Exception;
use PKP\invitation\models\InvitationModel;

class InvitationFactory
{
    protected static $invitations = [];
    private static $instance;

    public static function init($invitations): void
    {
        self::$invitations = $invitations;
        self::$instance = new self();
    }

    public static function getInstance(): InvitationFactory
    {
        return self::$instance;
    }

    public function createNew(string $type): Invitation
    {
        if (isset(self::$invitations[$type])) {
            return new self::$invitations[$type]();
        }

        throw new Exception("Invitation type '{$type}' not found.");
    }

    public function getExisting(string $type, InvitationModel $invitationModel): Invitation
    {
        if (isset(self::$invitations[$type])) {
            return new self::$invitations[$type]($invitationModel);
        }

        throw new Exception("Invitation type '{$type}' not found.");
    }
}
