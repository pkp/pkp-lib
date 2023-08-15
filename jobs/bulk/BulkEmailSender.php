<?php

/**
 * @file jobs/bulk/BulkEmailSender.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BulkEmailSender
 *
 * @ingroup jobs
 *
 * @brief Job to send bulk emails
 */

namespace PKP\jobs\bulk;

use APP\facades\Repo;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Mail;
use PKP\jobs\BaseJob;
use PKP\mail\Mailable;

class BulkEmailSender extends BaseJob
{
    use Batchable;

    /**
     * The maximum number of SECONDS a job should get processed before consider failed
     */
    public int $timeout = 180;

    /**
     * The user ids to send email
     */
    protected array $userIds;

    /**
     * The associated context id
     */
    protected int $contextId;

    /**
     * Mail subject
     */
    protected string $subject;

    /**
     * Mail body
     */
    protected string $body;

    /**
     * From email to send mail
     */
    protected object|array|string $fromEmail;

    /**
     * From name to send mail
     */
    protected mixed $fromName;

    /**
     * Create a new job instance.
     */
    public function __construct(array $userIds, int $contextId, string $subject, string $body, object|array|string $fromEmail, mixed $fromName)
    {
        parent::__construct();

        $this->userIds = $userIds;
        $this->contextId = $contextId;
        $this->subject = $subject;
        $this->body = $body;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    public function handle()
    {
        $users = Repo::user()
            ->getCollector()
            ->filterByContextIds([$this->contextId])
            ->filterByUserIds($this->userIds)
            ->getMany();

        foreach ($users as $user) {
            $mailable = new Mailable();
            $mailable
                ->from($this->fromEmail, $this->fromName)
                ->to($user->getEmail(), $user->getFullName())
                ->subject($this->subject)
                ->body($this->body);

            Mail::send($mailable);
        }
    }
}
