<?php

/**
 * @file classes/facades/Repo.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repo
 *
 * @brief This facade provides access to all Repositories in the application.
 *
 * A Repo contains all the methods needed to interact with an entity, such
 * as CRUD operations as well as utility methods to check status, locate items
 * and perform bulk actions.
 *
 * A Repository is a wrapper around an entity's DAO where additional business
 * logic can be performed. Use the Repository to coordinate actions across the
 * application, such as firing events, writing activity logs, or refreshing
 * cached data. The Repository should hand off data to the DAO to perform
 * basic crud operations.
 */

namespace PKP\facades;

use PKP\announcement\Repository as AnnouncementRepository;
use PKP\author\Repository as AuthorRepository;
use PKP\category\Repository as CategoryRepository;
use PKP\decision\Repository as DecisionRepository;
use PKP\emailTemplate\Repository as EmailTemplateRepository;
use PKP\highlight\Repository as HighlightRepository;
use PKP\institution\Repository as InstitutionRepository;
use PKP\invitation\repositories\Repository as InvitationRepository;
use PKP\jats\Repository as JatsRepository;
use PKP\job\repositories\FailedJob as FailedJobRepository;
use PKP\job\repositories\Job as JobRepository;
use PKP\log\event\Repository as EventLogRepository;
use PKP\log\Repository as EmailLogEntryRepository;
use PKP\note\Repository as NoteRepository;
use PKP\notification\Repository as NotificationRepository;
use PKP\query\Repository as QueryRepository;
use PKP\stageAssignment\Repository as StageAssignmentRepository;
use PKP\submissionFile\Repository as SubmissionFileRepository;
use PKP\userGroup\Repository as UserGroupRepository;

class Repo
{
    public static function announcement(): AnnouncementRepository
    {
        return app(AnnouncementRepository::class);
    }

    public static function author(): AuthorRepository
    {
        return app(AuthorRepository::class);
    }

    public static function decision(): DecisionRepository
    {
        return app()->make(DecisionRepository::class);
    }

    public static function emailTemplate(): EmailTemplateRepository
    {
        return app(EmailTemplateRepository::class);
    }

    public static function category(): CategoryRepository
    {
        return app(CategoryRepository::class);
    }

    public static function submissionFile(): SubmissionFileRepository
    {
        return app(SubmissionFileRepository::class);
    }

    public static function job(): JobRepository
    {
        return app()->make(JobRepository::class);
    }

    public static function failedJob(): FailedJobRepository
    {
        return app()->make(FailedJobRepository::class);
    }

    public static function institution(): InstitutionRepository
    {
        return app()->make(InstitutionRepository::class);
    }

    public static function userGroup(): UserGroupRepository
    {
        return app(UserGroupRepository::class);
    }

    public static function eventLog(): EventLogRepository
    {
        return app(EventLogRepository::class);
    }

    public static function invitation(): InvitationRepository
    {
        return app(InvitationRepository::class);
    }

    public static function highlight(): HighlightRepository
    {
        return app(HighlightRepository::class);
    }

    public static function jats(): JatsRepository
    {
        return app(JatsRepository::class);
    }

    public static function stageAssignment(): StageAssignmentRepository
    {
        return app(StageAssignmentRepository::class);
    }

    public static function emailLogEntry(): EmailLogEntryRepository
    {
        return app(EmailLogEntryRepository::class);
    }

    public static function notification(): NotificationRepository
    {
        return app(NotificationRepository::class);
    }

    public static function note(): NoteRepository
    {
        return app(NoteRepository::class);
    }

    public static function query(): QueryRepository
    {
        return app(QueryRepository::class);
    }
}
