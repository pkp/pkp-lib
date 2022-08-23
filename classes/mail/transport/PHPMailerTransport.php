<?php

/**
 * @file classes/mail/transport/PHPMailerTransport.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PHPMailerTransport.php
 *
 * @brief Transport/adapter to send with PHPMailer
 */

namespace PKP\mail\transport;

use APP\core\Application;
use PHPMailer\PHPMailer\PHPMailer;
use PKP\config\Config;
use PKP\core\PKPString;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Exception;
use ReflectionObject;

class PHPMailerTransport implements TransportInterface
{
    public const MAIL_WRAP = 76;

    /**
     * @inheritDoc
     */
    public function send(RawMessage $symfonyMessage, Envelope $envelope = null): ?SentMessage
    {
        if (!($symfonyMessage instanceof Email)) {
            throw new Exception('Can\'t send raw message with phpmailer');
        }

        $phpmailerMessage = $this->getPHPMailerMessage($symfonyMessage);
        try {
            $success = $phpmailerMessage->Send();
            if (!$success) {
                error_log($phpmailerMessage->ErrorInfo);
                return null;
            }
        } catch (Exception $e) {
            error_log($phpmailerMessage->ErrorInfo);
            return null;
        }

        return new SentMessage($symfonyMessage, $envelope ?? Envelope::create($symfonyMessage));
    }

    public function __toString(): string
    {
        return 'phpmailer';
    }

    /**
     * We won't send symfony message; transfer data to PHPMailer to send with mail()
     */
    protected function getPHPMailerMessage(Email $symfonyMessage): PHPMailer
    {
        $mailer = new PHPMailer();
        $mailer->isMail();
        $mailer->IsHTML();
        $mailer->Encoding = PHPMailer::ENCODING_BASE64;
        $mailer->CharSet = $symfonyMessage->getHtmlCharset() ?? 'utf-8';
        $mailer->XMailer = 'Public Knowledge Project Suite v3';
        $mailer->WordWrap = static::MAIL_WRAP;

        $request = Application::get()->getRequest();
        $fromAddresses = $symfonyMessage->getFrom();
        if (!empty($fromAddresses)) {
            $f = current($fromAddresses);
            if (Config::getVar('email', 'force_default_envelope_sender') && Config::getVar('email', 'default_envelope_sender') && Config::getVar('email', 'force_dmarc_compliant_from')) {
                /* If a DMARC compliant RFC5322.From was requested we need to promote the original RFC5322.From into a Reply-to header
                 * and then munge the RFC5322.From */
                $alreadyExists = false;
                foreach ($symfonyMessage->getReplyTo() as $r) {
                    if ($r->getAddress() === $f->getAddress()) {
                        $alreadyExists = true;
                        break;
                    }
                }

                if (!$alreadyExists) {
                    $mailer->addReplyTo($f->getAddress(), $f->getName());
                }

                $site = $request->getSite();

                // Munge the RFC5322.From
                if (Config::getVar('email', 'dmarc_compliant_from_displayname')) {
                    $patterns = ['#%n#', '#%s#'];
                    $replacements = [$f->getName(), $site->getLocalizedTitle()];
                    $fromName = preg_replace($patterns, $replacements, Config::getVar('email', 'dmarc_compliant_from_displayname'));
                } else {
                    $fromName = '';
                }
                $fromEmail = Config::getVar('email', 'default_envelope_sender');
            }
            // this sets both the envelope sender (RFC5321.MailFrom) and the From: header (RFC5322.From)
            $mailer->setFrom($fromEmail ?? $f->getAddress(), $fromName ?? $f->getName());
        }
        // Set the envelope sender (RFC5321.MailFrom)
        if (($s = $symfonyMessage->getSender()) != null) {
            $mailer->Sender = $s->toString();
        }
        foreach ($symfonyMessage->getReplyTo() as $r) {
            $mailer->addReplyTo($r->getAddress(), $r->getName());
        }
        foreach ($symfonyMessage->getTo() as $recipientInfo) {
            $mailer->addAddress($recipientInfo->getAddress(), $recipientInfo->getName());
        }
        foreach ($symfonyMessage->getCc() as $ccInfo) {
            $mailer->addCC($ccInfo->getAddress(), $ccInfo->getName());
        }
        foreach ($symfonyMessage->getBcc() as $bccInfo) {
            $mailer->addBCC($bccInfo->getAddress(), $bccInfo->getName());
        }
        $mailer->Subject = $symfonyMessage->getSubject();
        $mailer->Body = $symfonyMessage->getHtmlBody();
        $mailer->AltBody = PKPString::html2text($symfonyMessage->getHtmlBody());

        $remoteAddr = $mailer->secureHeader($request->getRemoteAddr());
        if ($remoteAddr != '') {
            $mailer->addCustomHeader("X-Originating-IP: ${remoteAddr}");
        }

        foreach ($this->getAttachments($symfonyMessage) as $attachmentInfo) {
            $mailer->addStringAttachment(
                $attachmentInfo['body'],
                $attachmentInfo['filename'],
                PHPMailer::ENCODING_BASE64,
                $attachmentInfo['mediaType'] . '/' . $attachmentInfo['subtype']
            );
        }

        return $mailer;
    }

    /**
     * Retrieve Symfony Message's assignments
     */
    protected function getAttachments(Email $message): array
    {
        $attachments = [];
        foreach ($message->getAttachments() as $dataPart) {
            $reflection = new ReflectionObject($dataPart);
            $attachment = [];
            foreach ($reflection->getProperties() as $property) {
                switch ($property->getName()) {
                    case 'filename':
                    case 'mediaType':
                        $property->setAccessible(true);
                        $attachment[$property->getName()] = $property->getValue($dataPart);
                        break;
                }
            }

            $attachment['subtype'] = $dataPart->getMediaSubtype();
            $attachment['body'] = $dataPart->getBody();
            $attachments[] = $attachment;
        }

        return $attachments;
    }
}
