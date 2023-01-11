<?php
/**
 * @file classes/publication/Repository.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class publication
 *
 * @brief Get publications and information about publications
 */

namespace APP\publication;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Facades\App;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\publication\Collector;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignmentDAO;
use PKP\user\User;

class Repository extends \PKP\publication\Repository
{
    /** @copydoc \PKP\submission\Repository::$schemaMap */
    public $schemaMap = maps\Schema::class;

    public function getCollector(): Collector
    {
        return App::makeWith(Collector::class, ['dao' => $this->dao]);
    }

    /** @copydoc PKP\publication\Repository::validate() */
    public function validate($publication, array $props, Submission $submission, Context $context): array
    {
        $errors = parent::validate($publication, $props, $submission, $context);

        $allowedLocales = $context->getSupportedSubmissionLocales();
        $primaryLocale = $submission->getLocale();
        $sectionDao = Application::get()->getSectionDAO(); /** @var SectionDAO $sectionDao */

        // Ensure that the specified section exists
        $section = null;
        if (isset($props['sectionId'])) {
            $section = $sectionDao->getById($props['sectionId']);
            if (!$section) {
                $errors['sectionId'] = [__('publication.invalidSection')];
            }
        }

        // Get the section so we can validate section abstract requirements
        if (!$section && !is_null($publication)) {
            $section = $sectionDao->getById($publication->getData('sectionId'));
        }

        // Only validate section settings for completed submissions
        if ($section && !$submission->getData('submissionProgress')) {

            // Require abstracts if the section requires them
            if (is_null($publication) && !$section->getData('abstractsNotRequired') && empty($props['abstract'])) {
                $errors['abstract'][$primaryLocale] = [__('author.submit.form.abstractRequired')];
            }

            if (isset($props['abstract']) && empty($errors['abstract'])) {

                // Require abstracts in the primary language if the section requires them
                if (!$section->getData('abstractsNotRequired')) {
                    if (empty($props['abstract'][$primaryLocale])) {
                        if (!isset($errors['abstract'])) {
                            $errors['abstract'] = [];
                        };
                        $errors['abstract'][$primaryLocale] = [__('author.submit.form.abstractRequired')];
                    }
                }

                // Check the word count on abstracts
                foreach ($allowedLocales as $localeKey) {
                    if (empty($props['abstract'][$localeKey])) {
                        continue;
                    }
                    $wordCount = PKPString::getWordCount($props['abstract'][$localeKey]);
                    $wordCountLimit = $section->getData('wordCount');
                    if ($wordCountLimit && $wordCount > $wordCountLimit) {
                        if (!isset($errors['abstract'])) {
                            $errors['abstract'] = [];
                        };
                        $errors['abstract'][$localeKey] = [__('publication.wordCountLong', ['limit' => $wordCountLimit, 'count' => $wordCount])];
                    }
                }
            }
        }

        return $errors;
    }

    /** @copydoc \PKP\publication\Repository::version() */
    public function version(Publication $publication): int
    {
        $newId = parent::version($publication);

        $context = Application::get()->getRequest()->getContext();

        $galleys = $publication->getData('galleys');
        $isDoiVersioningEnabled = $context->getData(Context::SETTING_DOI_VERSIONING);
        if (!empty($galleys)) {
            foreach ($galleys as $galley) {
                $newGalley = clone $galley;
                $newGalley->setData('id', null);
                $newGalley->setData('publicationId', $newId);
                if ($isDoiVersioningEnabled) {
                    $newGalley->setData('doiId', null);
                }
                Repo::galley()->add($newGalley);
            }
        }

        return $newId;
    }

    public function validatePublish(Publication $publication, Submission $submission, array $allowedLocales, string $primaryLocale): array
    {
        $errors = parent::validatePublish($publication, $submission, $allowedLocales, $primaryLocale);

        if (!$this->canCurrentUserPublish($submission->getId())) {
            $errors['authorCheck'] = __('author.submit.authorsCanNotPublish');
        }

        return $errors;
    }

    /** @copydoc \PKP\publication\Repository::setStatusOnPublish() */
    protected function setStatusOnPublish(Publication $publication)
    {
        // If the publish date is in the future, set the status to scheduled
        $datePublished = $publication->getData('datePublished');
        if ($datePublished && strtotime($datePublished) > strtotime(Core::getCurrentDate())) {
            $publication->setData('status', Submission::STATUS_SCHEDULED);
        } else {
            $publication->setData('status', Submission::STATUS_PUBLISHED);
        }

        // If there is no publish date, set it
        if (!$publication->getData('datePublished')) {
            $publication->setData('datePublished', Core::getCurrentDate());
        }
    }

    /** @copydoc \PKP\publication\Repository::delete() */
    public function delete(Publication $publication)
    {
        $galleys = Repo::galley()->getCollector()
            ->filterByPublicationIds([$publication->getId()])
            ->getMany();

        foreach ($galleys as $galley) {
            Repo::galley()->delete($galley);
        }


        parent::delete($publication);
    }

    /**
     * Set the DOI of a related preprint
     */
    public function relate(Publication $publication, int $relationStatus, ?string $vorDoi = '')
    {
        if ($relationStatus !== Publication::PUBLICATION_RELATION_PUBLISHED) {
            $vorDoi = '';
        }
        $this->edit($publication, [
            'relationStatus' => $relationStatus,
            'vorDoi' => $vorDoi,
        ]);
    }

    /**
     * Check if the current user can publish this submission
     *
     * Do not use this as a general authorization check. This does not
     * check whether the current user is actually assigned to the
     * submission in a role that is allowed to publish. It is only used
     * in a few places to see if the current user is an author who can
     * publish, based on automated moderation tools that use the hook
     * Publication::canAuthorPublish.
     *
     * @deprecated 3.4
     */
    public function canCurrentUserPublish(int $submissionId, ?User $user = null): bool
    {
        $user = $user ?? Application::get()->getRequest()->getUser();

        // Check if current user is an author
        $isAuthor = false;
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $submitterAssignments = $stageAssignmentDao->getBySubmissionAndRoleIds($submissionId, [Role::ROLE_ID_AUTHOR]);
        while ($assignment = $submitterAssignments->next()) {
            if ($user->getId() == $assignment->getUserId()) {
                $isAuthor = true;
            }
        }

        // By default authors can not publish, but this can be overridden in screening plugins with the hook Publication::canAuthorPublish
        if ($isAuthor) {
            return (bool) Hook::call('Publication::canAuthorPublish', [$this]);
        }

        // If the user is not an author, has to be an editor, return true
        return true;
    }

    /**
     * @copydoc \PKP\publication\Repository::getErrorMessageOverrides
     */
    protected function getErrorMessageOverrides(): array
    {
        $overrides = parent::getErrorMessageOverrides();
        $overrides['relationStatus'] = __('validation.invalidOption');
        return $overrides;
    }

    /**
     * Create all DOIs associated with the publication
     */
    protected function createDois(Publication $newPublication): void
    {
        $submission = Repo::submission()->get($newPublication->getData('submissionId'));
        Repo::submission()->createDois($submission);
    }
}
