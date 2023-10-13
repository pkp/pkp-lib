<?php

declare(strict_types=1);

/**
 * @file classes/core/exceptions/StoreTemporaryFileException.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StoreTemporaryFileException
 *
 * @brief Use this exception when an error is encountered while moving
 *   a temporary file to a permanent place.
 */

namespace PKP\core\exceptions;

use Exception;
use PKP\core\DataObject;
use PKP\file\TemporaryFile;
use PKP\user\User;

class StoreTemporaryFileException extends Exception
{
    public function __construct(public TemporaryFile $temporaryFile, public string $targetPath, public ?User $user, public ?DataObject $dataObject)
    {
        $message = `Unable to store temporary file {$temporaryFile->getFilePath()} in {$targetPath}.`;
        if ($user) {
            $message .= ` File was uploaded by {$temporaryFile->getUserId()}. The current user is {$user->getId()}.`;
        }
        if ($dataObject) {
            $class = get_class($dataObject);
            $message .= ` Handling {$class} id {$dataObject->getId()}.`;
        }
        parent::__construct($message);
    }
}
