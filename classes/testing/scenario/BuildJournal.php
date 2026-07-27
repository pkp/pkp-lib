<?php

/**
 * @file classes/testing/scenario/BuildJournal.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BuildJournal
 *
 * @ingroup testing
 *
 * @brief Failure hygiene for a scenario build.
 *
 * A partially built scenario is worse than no scenario: a test that finds a
 * submission "in review" with no review round wastes an investigation. So the
 * builder records every entity it creates, and on failure walks the journal
 * backwards, deleting through the same repositories that created them.
 *
 * If a compensating delete itself fails, the surviving entity is TAGGED as an
 * orphan (its title/name is prefixed) rather than left looking usable, and the
 * failure response names it.
 */

namespace PKP\testing\scenario;

use APP\facades\Repo;
use Throwable;

class BuildJournal
{
    public const ORPHAN_PREFIX = '[ORPHAN] ';

    /** @var array<int, array{type: string, id: int}> */
    protected array $created = [];

    /** @var array<int, string> */
    protected array $orphans = [];

    public function recordContext(int $id): void
    {
        $this->created[] = ['type' => 'context', 'id' => $id];
    }

    public function recordSubmission(int $id): void
    {
        $this->created[] = ['type' => 'submission', 'id' => $id];
    }

    public function recordUser(int $id): void
    {
        $this->created[] = ['type' => 'user', 'id' => $id];
    }

    /**
     * Entities tagged as orphans because they could not be deleted.
     *
     * @return array<int, string>
     */
    public function orphans(): array
    {
        return $this->orphans;
    }

    /**
     * Undo everything this build created, newest first.
     */
    public function rollBack(): void
    {
        foreach (array_reverse($this->created) as $entity) {
            try {
                match ($entity['type']) {
                    'submission' => $this->deleteSubmission($entity['id']),
                    'context' => $this->deleteContext($entity['id']),
                    'user' => $this->deleteUser($entity['id']),
                };
            } catch (Throwable $e) {
                $this->orphans[] = "{$entity['type']}:{$entity['id']} ({$e->getMessage()})";
                $this->tagOrphan($entity['type'], $entity['id']);
            }
        }

        $this->created = [];
    }

    protected function deleteSubmission(int $id): void
    {
        $submission = Repo::submission()->get($id);

        if ($submission) {
            Repo::submission()->delete($submission);
        }
    }

    protected function deleteContext(int $id): void
    {
        $context = app()->get('context')->get($id);

        if ($context) {
            app()->get('context')->delete($context);
        }
    }

    protected function deleteUser(int $id): void
    {
        $user = Repo::user()->get($id);

        if ($user) {
            Repo::user()->delete($user);
        }
    }

    /**
     * Last resort: make the survivor visibly unusable rather than plausibly seeded.
     */
    protected function tagOrphan(string $type, int $id): void
    {
        try {
            if ($type === 'submission') {
                $submission = Repo::submission()->get($id);
                $publication = $submission?->getCurrentPublication();

                if ($publication) {
                    $locale = $submission->getData('locale');
                    Repo::publication()->edit($publication, [
                        'title' => [$locale => static::ORPHAN_PREFIX . $publication->getLocalizedTitle($locale)],
                    ]);
                }
            } elseif ($type === 'context') {
                $context = app()->get('context')->get($id);

                if ($context) {
                    $locale = $context->getPrimaryLocale();
                    app()->get('context')->edit($context, [
                        'name' => [$locale => static::ORPHAN_PREFIX . $context->getLocalizedName($locale)],
                    ], app()->get('request'));
                }
            }
        } catch (Throwable) {
            // Nothing further can be done; the orphan list already names it.
        }
    }
}
