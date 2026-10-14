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
use Illuminate\Database\Eloquent\Model;
use PKP\core\DataObject;
use PKP\file\TemporaryFile;
use PKP\user\User;

class StoreTemporaryFileException extends Exception
{
    public function __construct(public TemporaryFile $temporaryFile, public string $targetPath, public ?User $user, public DataObject|Model|null $dataObject)
    {
        $message = "Unable to store temporary file {$temporaryFile->getFilePath()} in {$targetPath}.";
        if ($user) {
            $message .= " File was uploaded by {$temporaryFile->getUserId()}. The current user is {$user->getId()}.";
        }
        if ($dataObject) {
            $id = match(true) {
                $dataObject instanceof DataObject => $dataObject->getId(),
                $dataObject instanceof Model => $dataObject->id,
            };
            $message .= ' Handling ' . get_class($dataObject) . " {$id}.";
        }
        parent::__construct($message);
    }

    public function getDataObjectId(): ?int
    {
        if (!isset($this->dataObject)) {
            return null;
        }

        return match(true) {
            $this->dataObject instanceof DataObject => $this->dataObject->getId(),
            $this->dataObject instanceof Model => $this->dataObject->getKey()
        };
    }
}
