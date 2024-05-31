<?php

/**
 * @file invitation/core/PKPInvitationFactory.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInvitationFactory
 *
 * @brief Invitation Factory
 */

namespace PKP\invitation\core;

use Exception;
use PKP\invitation\core\Invitation;
use PKP\invitation\models\InvitationModel;

class PKPInvitationFactory 
{
    protected static $invitations = [];
    private static $instance;

    public static function init($invitations): void 
    {
        self::$invitations = $invitations;
        self::$instance = new self();
    }

    public static function getInstance(): PKPInvitationFactory 
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