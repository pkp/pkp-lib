<?php

/**
 * @file classes/mail/mailables/ReviewRemind.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRemind
 *
 * @ingroup mail_mailables
 *
 * @brief Email is sent by an editor to a reviewer to remind about the review request
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use Illuminate\Mail\Mailable as IlluminateMailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\OneClickReviewerAccess;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;

class DispatchInvitation extends IlluminateMailable
{
    // protected static ?string $name = 'mailable.reviewRemind.name';
    // protected static ?string $description = 'mailable.reviewRemind.description';
    // protected static ?string $emailTemplateKey = 'REVIEW_REMIND';
    // protected static bool $supportsTemplates = true;
    // protected static array $groupIds = [self::GROUP_REVIEW];
    // protected static array $fromRoleIds = [Role::ROLE_ID_SUB_EDITOR];
    // protected static array $toRoleIds = [Role::ROLE_ID_REVIEWER];

    protected string $acceptURL;
    protected string $declineURL;
    protected string $message;
    protected string $emailSubject;

    public function __construct(string $message, string $emailSubject, string $acceptURL, string $declineURL)
    {
        // parent::__construct(func_get_args());

        $this->acceptURL = $acceptURL;
        $this->declineURL = $declineURL;
        $this->message = $message;
        $this->emailSubject = $emailSubject;
    }

    // /**
    //  * Override the setData method to add the one-click access
    //  * URL for the reviewer
    //  */
    // public function setData(?string $locale = null): void
    // {
    //     parent::setData($locale);

    //     $this->setOneClickAccessUrl($this->context, $this->reviewAssignment);
    // }

    public function build()
    {
        $message = $this->message . ' -> ACCEPT::' . $this->acceptURL . ' DECLINE::' . $this->declineURL;
        
        // Should be taken either from given Journal values, or from 
        // the user that sent the invitation. 
        // Could it be different for different tyoes of invitations?
        $fromAddress = 'defstat@gmail.com';
        $fromName = 'Dimitris Efstathiou';

        return $this->from($fromAddress, $fromName)
                    ->subject($this->emailSubject)
                    ->text($message); 
    }
}
