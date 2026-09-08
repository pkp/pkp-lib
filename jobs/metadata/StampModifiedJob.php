<?php

declare(strict_types=1);

/**
 * @file classes/jobs/metadata/StampModifiedJob.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StampModifiedJob
 *
 * @ingroup jobs
 *
 * @brief Carries one hop of the last_modified cascade towards the submission.
 */

namespace PKP\jobs\metadata;

use APP\facades\Repo;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;
use PKP\observers\events\PublicationMetadataChanged;

class StampModifiedJob extends BaseJob
{
    public const FROM_AUTHOR = 'author';
    public const FROM_CITATION = 'citation';
    public const FROM_PUBLICATION = 'publication';

    /**
     * @param string $origin One of the FROM_* constants
     * @param int $parentId Id of the entity one level up from the origin
     */
    public function __construct(
        protected string $origin,
        protected int $parentId
    ) {
        parent::__construct();

        // BaseJob's constructor reads the default connection from config, which
        // is "database". A queued job is never processed when job_runner is off
        // and no worker is running, leaving last_modified unstamped. Forcing the
        // sync connection keeps the cascade correct in that deployment.
        $this->connection = 'sync';
    }

    public function handle(): void
    {
        match ($this->origin) {
            self::FROM_AUTHOR, self::FROM_CITATION => $this->cascadeToSubmission(),
            self::FROM_PUBLICATION => $this->stampSubmission(),
        };
    }

    /**
     * Emit the next event in the chain, so that other consumers can hook into
     * publication-level changes without depending on this job.
     */
    protected function cascadeToSubmission(): void
    {
        $publication = Repo::publication()->get($this->parentId);

        if (!$publication) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        event(new PublicationMetadataChanged($publication));
    }

    protected function stampSubmission(): void
    {
        Repo::submission()->stampModified($this->parentId);
    }
}
