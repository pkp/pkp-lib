<?php

/**
 * @file mail/mailables/FormEmailData.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormEmailData
 * @ingroup mail_mailables
 *
 * @brief Serves to transfer data from the form/request to mailable
 */

namespace PKP\mail\mailables;

use Illuminate\Support\LazyCollection;
use PKP\facades\Repo;
use PKP\user\User;

class FormEmailData
{
    protected string $body;

    protected string $subject;

    protected bool $skipEmail = false;

    protected array $recipientIds = [];

    protected bool $separateRecipients = false;

    protected int $senderId;

    protected array $variables = [];

    public function setBody(string $body) : FormEmailData
    {
        $this->body = $body;
        return $this;
    }

    public function setSubject(?string $subject) : FormEmailData
    {
        if ($subject) {
            $this->subject = $subject;
        }
        return $this;
    }

    public function skipEmail(bool $skip) : FormEmailData
    {
        $this->skipEmail = $skip;
        return $this;
    }

    public function setRecipientIds(array $userIds) : FormEmailData
    {
        $this->recipientIds = $userIds;
        return $this;
    }

    public function setSenderId(int $userId) : FormEmailData
    {
        $this->senderId = $userId;
        return $this;
    }

    public function addVariables(array $variablesToAdd) : FormEmailData
    {
        $this->variables = $variablesToAdd + $this->variables;
        return $this;
    }

    public function shouldBeSkipped() {
        return $this->skipEmail;
    }

    public function getRecipients(int $contextId) : LazyCollection
    {
        return Repo::user()->getMany(
            Repo::user()->getCollector()
                ->filterByUserIds($this->recipientIds)
                ->filterByContextIds([$contextId])
        );
    }

    public function getSender() : ?User
    {
        return Repo::user()->get($this->senderId);
    }

    public function getVariables(int $userId = null) : array
    {
        return $userId ? $this->variables[$userId] : $this->variables;
    }
}
