<?php

/**
 * @file classes/mail/traits/CapturableReply.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CapturableReply
 *
 * @ingroup mail_traits
 *
 * @brief Mailable trait to add support for capturable replies
 */

namespace PKP\mail\traits;

use Illuminate\Mail\Mailables\Headers;
use PKP\config\Config;

trait CapturableReply
{
    protected ?string $messageId = null;
    protected ?string $inReplyTo = null;
    protected array $references = [];

    /**
     * Adds headers that can be used to determine what a reply is in response to
     */
    public function headers(): Headers
    {
        $textHeaders = [];

        if ($this->inReplyTo) {
            $textHeaders['In-Reply-To'] = $this->inReplyTo;
        }

        return new Headers(
            messageId: $this->messageId ?: null,
            references: $this->references,
            text: $textHeaders,
        );
    }

    public function setupCapturableReply()
    {
        if ($replyTo = Config::getVar('email', 'reply_to_address')) {
            $this->replyTo($replyTo);
        }
    }

    public function allowCapturableReply(
        ?string $messageId,
        ?string $inReplyTo = null,
        array $references = []
    ): static {
        $this->messageId = $messageId;
        $this->inReplyTo = $inReplyTo;
        $this->references = $references;

        return $this;
    }
}
