<?php

declare(strict_types=1);
namespace PKP\observers\listeners;

/**
 * @file classes/observers/listeners/StampSubmissionModified.php
 *
 * @class StampSubmissionModified
 *
 * @ingroup core
 *
 * @brief Listener that stamps `last_modified` on the parent Submission whenever
 *        metadata changes (Author, Citation, ...). Fixes pkp-lib#13074: editing
 *        or removing Author/Citation records never updated the parent
 *        Submission/Publication's `last_modified`, which PKPOAIDAO uses directly
 *        to compute the OAI-PMH datestamp for selective harvesting.
 *
 *        This is a separate listener on the existing `MetadataChanged` event,
 *        added alongside `MetadataChangedListener` (search reindexing) without
 *        modifying it, since Laravel's dispatcher supports multiple listeners
 *        per event.
 */



use APP\facades\Repo;
use PKP\observers\events\MetadataChanged;

class StampSubmissionModified
{
    /**
     * Handle the listener call.
     *
     * Design note: this runs synchronously, in-process, rather than being
     * queued as a Job (unlike MetadataChangedListener's MetadataChangedJob).
     * Chosen deliberately, not by default:
     *
     * - The write is trivial (a single field stamp + update on an
     *   already-loaded Submission), well below the cost that justifies
     *   queuing.
     * - Queuing here would make the fix's correctness depend on the job
     *   queue being actively processed. In the default PKP setup (no
     *   dedicated worker/cron), PKP\queue\JobRunner runs queued jobs
     *   synchronously at the end of the same request anyway, so queuing
     *   would buy no real decoupling in the common case. But if an
     *   installation runs `job_runner = Off` with no worker/cron replacing
     *   it (as this project's own dev environment does, deliberately, to
     *   get clean query-count baselines - see
     *   OJS_Profilazione_Query_Metodo_Caching.md), a queued job would
     *   simply never run, silently defeating the fix this listener exists
     *   to provide.
     * - This listener's job is specifically to guarantee the timestamp
     *   updates; making that depend on optional infrastructure would work
     *   against the point of the fix.
     *
     * Scope boundary, not a pending revision: if this event is later reused
     * as an invalidation trigger for the OJS 3.5 core caching proposal
     * (Layer 3, tag-based invalidation), that would be implemented as its
     * own, autonomous listener, separate from this one, not as a change to
     * this class. This listener's responsibility is bounded to correctly
     * persisting `last_modified`; reading that value to decide what to
     * invalidate is a distinct concern belonging to that later work. The
     * synchronous choice made here is scoped to this listener's own job:
     * the public front-end/anonymous-traffic trade-offs relevant to a future
     * cache-invalidation listener are different and would need their own
     * evaluation, on their own terms, when that work starts.
     */
    public function handle(MetadataChanged $event)
    {
        Repo::submission()->stampModified($event->submission);
    }
}
