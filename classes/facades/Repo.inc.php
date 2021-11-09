<?php

/**
 * @file classes/facade/Repo.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repo
 *
 * @brief Extends the base Repo facade with any overrides for OPS
 */

namespace APP\facades;

use APP\publication\Repository as PublicationRepository;
use APP\submission\Repository as SubmissionRepository;
use APP\submissionFile\Repository as SubmissionFileRepository;
use PKP\user\Repository as UserRepository;

class Repo extends \PKP\facades\Repo
{
    public static function publication(): PublicationRepository
    {
        return app()->make(PublicationRepository::class);
    }

    public static function submission(): SubmissionRepository
    {
        return app()->make(SubmissionRepository::class);
    }

    public static function user(): UserRepository
    {
        return app()->make(UserRepository::class);
    }

    public static function submissionFiles(): SubmissionFileRepository
    {
        return app()->make(SubmissionFileRepository::class);
    }
}
