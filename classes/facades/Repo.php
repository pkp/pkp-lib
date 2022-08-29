<?php

/**
 * @file classes/facades/Repo.php
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

use APP\decision\Repository as DecisionRepository;
use APP\doi\Repository as DoiRepository;
use APP\publication\Repository as PublicationRepository;
use APP\submission\Repository as SubmissionRepository;
use APP\submissionFile\Repository as SubmissionFileRepository;
use PKP\galley\Repository as GalleyRepository;
use PKP\user\Repository as UserRepository;

class Repo extends \PKP\facades\Repo
{
    public static function decision(): DecisionRepository
    {
        return app(DecisionRepository::class);
    }

    public static function doi(): DoiRepository
    {
        return app(DoiRepository::class);
    }

    public static function galley(): GalleyRepository
    {
        return app(GalleyRepository::class);
    }

    public static function publication(): PublicationRepository
    {
        return app(PublicationRepository::class);
    }

    public static function submission(): SubmissionRepository
    {
        return app(SubmissionRepository::class);
    }

    public static function user(): UserRepository
    {
        return app(UserRepository::class);
    }

    public static function submissionFile(): SubmissionFileRepository
    {
        return app(SubmissionFileRepository::class);
    }
}
