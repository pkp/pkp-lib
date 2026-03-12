<?php

/**
 * @file components/UserCommentComponent.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class userCommentComponent
 *
 * @ingroup classes_components
 *
 * @brief A class to prepare configurations for pkpUserComment UI component.
 */

namespace PKP\components;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\DB;
use PKP\userComment\UserComment;

class UserCommentComponent
{
    private int $allCommentsCount;
    private Enumerable $publishedPublication;

    private array $commentsCountPerPublication;

    private Submission $submission;
    private Request $request;

    /**
     * @param Submission $submission - The submission for which the user comment component is being prepared.
     * @param Request $request - The current request object.
     */
    public function __construct(Submission $submission, Request $request)
    {
        $this->request = $request;
        $this->submission = $submission;

        // get all published publications for this submission
        $this->publishedPublication = collect();
        foreach (array_reverse($submission->getPublishedPublications()) as $publishedPublication) {
            $this->publishedPublication->add([
                'id' => $publishedPublication->getId(),
                'version' => $publishedPublication->getData('versionString')
            ]);
        }


        // Fetch data needed to calculate the count of comments for each publication and the total count of all comments across publications.
        $commentsForAllPublications = UserComment::withPublicationIds($this->publishedPublication->pluck('id')->all())
            ->where(function ($query) {
                $query->where('is_approved', true);
                $userId = $this->request->getUser()?->getId();

                // A user can see their unapproved comments for a given publication, so we include those to be able to properly calculate
                // the count number shown next to the "Show More" button in the front-office UI. This number should reflect the total remaining approved comments + the user's remaining unapproved comments (if the user is logged in) that can be viewed.
                if ($userId) {
                    $query->orWhere('user_id', $userId);
                }
            })
            ->select(['publication_id', 'is_approved'])
            ->get();

        $this->commentsCountPerPublication = $commentsForAllPublications
            ->groupBy('publication_id')
            ->map
            ->count()
            ->toArray();

        $this->allCommentsCount = $commentsForAllPublications
            ->filter(fn($comment) => $comment->is_approved)
            ->count();
    }

    /**
     * Get the SVG icons required by the pkpUserComment component.
     */
    public function getSvgIcons(): array
    {
        return [
            'Error',
            'Help',
            'MoreOptions',
            'Orcid',
            'OrcidUnauthenticated',
            'ChevronDown'
        ];
    }

    /**
     * Get the locale keys to expose in the pkpUserComment component.
     *
     */
    public function getLocaleKeys(): array
    {
        return [
            'userComment.discussionClosed',
            'userComment.awaitingApprovalNotice',
            'userComment.report.reason',
            'common.cancel',
            'userComment.reportCommentBy',
            'userComment.reportCommentByUserWithAffiliation',
            'userComment.report',
            'userComment.showMore',
            'form.submit',
            'userComment.login',
            'userComment.commentOnThisPublication',
            'userComment.versionWithCount',
            'common.delete',
            'userComment.deleteCommentConfirmation',
            'userComment.deleteComment',
            'userComment.addYourComment',
            'userComment.allComments',
            'userComment.comments',
            'userComment.reportComment',
        ];
    }

    /**
     * Get the URL to the login page.
     *
     * @return string
     */
    public function getLoginUrl()
    {
        return $this->request->getDispatcher()
            ->url(
                $this->request,
                Application::ROUTE_PAGE,
                null,
                'login',
                null,
                null,
                ['source' => $this->request->getRequestPath() . '#public-comments']
            );
    }

    /**
     * Get the configuration for the pkpUserComment component.
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'publications' => $this->publishedPublication->values(),
            'latestPublicationId' => $this->submission->getCurrentPublication()->getId(),
            'itemsPerPage' => Repo::userComment()->getPerPage(),
            'loginUrl' => $this->getLoginUrl(),
            'allCommentsCount' => $this->allCommentsCount,
            'commentsCountPerPublication' => $this->commentsCountPerPublication,
        ];
    }

    /**
     * Get the total count of all comments across all publications associated with the submission.
     *
     */
    public function getAllCommentsCount(): int
    {
        return $this->allCommentsCount;
    }
}
