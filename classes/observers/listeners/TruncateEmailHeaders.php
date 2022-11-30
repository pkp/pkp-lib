<?php

/**
 * @file classes/observers/listeners/TruncateEmailHeaders.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TruncateEmailHeaders
 * @ingroup observers_listeners
 *
 * @brief Truncates gracefully From and To email header fields if they exceed 256 symbols limit
 */

namespace PKP\observers\listeners;


use Illuminate\Events\Dispatcher;
use PKP\mail\Mailer;
use PKP\observers\events\MessageSendingContext;
use PKP\observers\events\MessageSendingSite;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mime\Address;

class TruncateEmailHeaders
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            MessageSendingContext::class,
            self::class . '@handleEmails'
        );

        $events->listen(
            MessageSendingSite::class,
            self::class . '@handleEmails'
        );
    }

    /**
     * Check the length of the body of email header address-related fields and remove the name if it exceeds the limit
     */
    public function handleEmails(MessageSendingSite|MessageSendingContext $event): void
    {
        $transport = $event->mailer->getSymfonyTransport();

        if ($transport::class !== SendmailTransport::class) {
            return;
        }

        $headers = $event->message->getHeaders();

        if ($sender = $event->message->getSender()) {
            if ($senderTruncatedAddress = $this->truncate([$sender])) {
                $headers->get('Sender')->setBody($senderTruncatedAddress);
            }
        }

        if ($returnPath = $event->message->getReturnPath()) {
            if ($returnPathTruncatedAddress = $this->truncate([$returnPath])) {
                $headers->get('Return-Path')->setBody($returnPathTruncatedAddress);
            }
        }

        if ($fromTruncatedAddresses = $this->truncate($event->message->getFrom())) {
            $headers->get('From')->setBody($fromTruncatedAddresses);
        }

        if ($replyToTruncatedAddresses = $this->truncate($event->message->getReplyTo())) {
            $headers->get('Reply-To')->setBody($replyToTruncatedAddresses);
        }

        if ($toTruncatedAddresses = $this->truncate($event->message->getTo())) {
            $headers->get('To')->setBody($toTruncatedAddresses);
        }

        if ($ccTruncatedAddresses = $this->truncate($event->message->getCc())) {
            $headers->get('Cc')->setBody($ccTruncatedAddresses);
        }

        if ($bccTruncatedAddresses = $this->truncate($event->message->getBcc())) {
            $headers->get('Bcc')->setBody($bccTruncatedAddresses);
        }
    }

    /**
     * @param Address[] $addresses
     * @return Address[]|false if any mailbox (name + address) exceeds 210 symbols limit, removes the name from it
     * or returns false if neither exceeds the limit
     * @see https://github.com/pkp/pkp-lib/issues/7239
     */
    private function truncate(array $addresses): array|bool
    {
        $validAddresses = [];
        $hasTruncated = null;
        foreach ($addresses as $address) {
            $encodedAddress = quoted_printable_encode($address->toString());
            if (strlen($encodedAddress) > Mailer::SENDMAIL_ADDRESS_LIMIT) {
                $validAddresses[] = new Address($address->getAddress());
                $hasTruncated ??= true;
                continue;
            }
            $validAddresses[] = $address;
        }

        if ($hasTruncated) {
            return $validAddresses;
        }

        return false;
    }
}
