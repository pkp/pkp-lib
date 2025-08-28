<?php

/**
 * @file classes/mail/mailables/ReviewerRequest.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRequest
 *
 * @ingroup mail_mailables
 *
 * @brief An email send to a reviewer with a request to accept or decline a task to review a submission
 */

namespace PKP\mail\mailables;

use APP\facades\Repo;
use Carbon\Carbon;
use Illuminate\Mail\Mailables\Attachment;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use Sabre\VObject;

class ReviewRequest extends Mailable
{
    use Recipient;
    use Sender;
    use Configurable;

    protected static ?string $name = 'mailable.reviewRequest.name';
    protected static ?string $description = 'mailable.reviewRequest.description';
    protected static ?string $emailTemplateKey = 'REVIEW_REQUEST';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static bool $canDisable = true;
    protected static array $fromRoleIds = [Role::ROLE_ID_SUB_EDITOR];
    protected static array $toRoleIds = [Role::ROLE_ID_REVIEWER];

    protected ReviewAssignment $reviewAssignment;

    public function __construct(Context $context, PKPSubmission $submission, ReviewAssignment $reviewAssignment)
    {
        $this->reviewAssignment = $reviewAssignment;
        parent::__construct(func_get_args());
    }

    public function attachments(): array
    {
        // If a machine reply-to email address was not specified, do not attach an ical invite.
        if (!Config::getVar('email', 'reply_to_address')) {
            return [];
        }

        $dateDue = $this->reviewAssignment->getDateDue();
        $vCalendar = new VObject\Component\VCalendar([
            'VEVENT' => [
                'SUMMARY' => __(static::$name),
                'DTSTART' => Carbon::now(),
                'DTEND' => $dateDue ? new Carbon($dateDue) : null,
                'UID' => uniqid('reviewAssignment'),
            ]
        ]);

        $reviewerUser = Repo::user()->get($this->reviewAssignment->getReviewerId());
        $vCalendar->VEVENT->add('ATTENDEE', $reviewerUser->getEmail(), [
            'CN' => $reviewerUser->getFullName(),
            'RSVP' => 'TRUE',
        ]);

        $request = PKPApplication::get()->getRequest();
        $user = $request->getUser();
        $vCalendar->VEVENT->add('ORGANIZER', Config::getVar('email', 'reply_to_address'), [
            'CN' => $user->getFullName(),
        ]);

        $vCalendar->PRODID = '-//PKP//Public Knowledge Project//EN';

        return [
            Attachment::fromData(fn () => $vCalendar->serialize(), 'invite.ics')
                ->withMime('text/calendar; charset="utf-8"; method=REQUEST')
        ];
    }
}
