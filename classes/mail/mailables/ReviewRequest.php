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
use Jsvrcek\ICS\CalendarExport;
use Jsvrcek\ICS\CalendarStream;
use Jsvrcek\ICS\Model\Calendar;
use Jsvrcek\ICS\Model\CalendarEvent;
use Jsvrcek\ICS\Model\Relationship\Attendee;
use Jsvrcek\ICS\Model\Relationship\Organizer;
use Jsvrcek\ICS\Utility\Formatter;
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

        $event = new CalendarEvent();
        $event->setStart(new \DateTime())
            ->setSummary(__(static::$name))
            ->setUid(uniqid('reviewAssignment'));

        if ($dateDue = $this->reviewAssignment->getDateDue()) {
            $event->setEnd(new Carbon($dateDue));
        }

        $reviewerUser = Repo::user()->get($this->reviewAssignment->getReviewerId());
        $attendee = new Attendee(new Formatter());
        $attendee->setValue($reviewerUser->getEmail())
            ->setName($reviewerUser->getFullName())
            ->setRsvp('TRUE');
        $event->addAttendee($attendee);

        $request = PKPApplication::get()->getRequest();
        $user = $request->getUser();
        $organizer = new Organizer(new Formatter());
        $organizer->setValue(Config::getVar('email', 'reply_to_address'))
            ->setName($user->getFullName());
        $event->setOrganizer($organizer);

        $calendar = new Calendar();
        $calendar->setProdId('-//PKP//Public Knowledge Project//EN')
            ->addEvent($event);

        $calendarExport = new CalendarExport(new CalendarStream(), new Formatter());
        $calendarExport->addCalendar($calendar);

        return [
            Attachment::fromData(fn () => $calendarExport->getStream(), 'invite.ics')
                ->withMime('text/calendar; charset="utf-8"; method=REQUEST')
        ];
    }
}
