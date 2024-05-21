<?php

/**
 * @file controllers/grid/users/reviewer/ReviewerGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerGridRow
 *
 * @ingroup controllers_grid_users_reviewer
 *
 * @brief Reviewer grid row definition
 */

namespace PKP\controllers\grid\users\reviewer;

use APP\facades\Repo;
use PKP\controllers\grid\GridRow;
use PKP\core\PKPApplication;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RedirectConfirmationModal;
use PKP\security\Validation;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewerGridRow extends GridRow
{
    /** @var bool Is the current user assigned as an author to this submission */
    public $_isCurrentUserAssignedAuthor;

    /**
     * Constructor
     *
     * @param bool $isCurrentUserAssignedAuthor Is the current user assigned as an
     *  author to this submission?
     */
    public function __construct($isCurrentUserAssignedAuthor)
    {
        parent::__construct();
        $this->_isCurrentUserAssignedAuthor = $isCurrentUserAssignedAuthor;
    }

    //
    // Overridden methods from GridRow
    //
    /**
     * @copydoc GridRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        parent::initialize($request, $template);

        // Retrieve the submission id from the request
        // These parameters need not be validated as we're just
        // passing them along to another request, where they will be
        // checked before they're used.
        $submissionId = (int) $request->getUserVar('submissionId');
        $stageId = (int) $request->getUserVar('stageId');
        $round = (int) $request->getUserVar('round');

        // Authors can't perform any actions on anonymous reviews
        $reviewAssignment = $this->getData(); /** @var ReviewAssignment $reviewAssignment */
        $isReviewAnonymous = in_array($reviewAssignment->getReviewMethod(), [ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS, ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS]);
        if ($this->_isCurrentUserAssignedAuthor && $isReviewAnonymous) {
            return;
        }

        // Is this a new row or an existing row?
        $rowId = $this->getId();
        if (!empty($rowId) && is_numeric($rowId)) {
            // Only add row actions if this is an existing row
            $router = $request->getRouter();
            $actionArgs = [
                'submissionId' => $submissionId,
                'reviewAssignmentId' => $rowId,
                'stageId' => $stageId,
                'round' => $round
            ];

            // read or upload a review
            $submission = Repo::submission()->get($submissionId);
            if (!$reviewAssignment->getCancelled()) {
                $this->addAction(
                    new LinkAction(
                        'readReview',
                        new AjaxModal(
                            $router->url($request, null, null, 'readReview', null, $actionArgs),
                            __('editor.review.reviewDetails') . ': ' . $submission->getCurrentPublication()->getLocalizedTitle(null, 'html'),
                            'modal_information'
                        ),
                        __('editor.review.reviewDetails'),
                        'more_info'
                    )
                );
            }

            $this->addAction(
                new LinkAction(
                    'email',
                    new AjaxModal(
                        $router->url($request, null, null, 'sendEmail', null, $actionArgs),
                        __('editor.review.emailReviewer'),
                        'modal_email'
                    ),
                    __('editor.review.emailReviewer'),
                    'notify'
                )
            );

            if (!$this->_isCurrentUserAssignedAuthor) {
                if ($reviewAssignment->canResendReviewRequest()) {
                    $this->addAction(
                        new LinkAction(
                            'resendRequestReviewer',
                            new AjaxModal(
                                $router->url($request, null, null, 'resendRequestReviewer', null, $actionArgs),
                                __('editor.review.resendRequestReviewer'),
                                'modal_add'
                            ),
                            __('editor.review.resendRequestReviewer'),
                            'add'
                        )
                    );
                }

                if (!$reviewAssignment->getCancelled()) {
                    $this->addAction(new LinkAction(
                        'manageAccess',
                        new AjaxModal(
                            $router->url($request, null, null, 'editReview', null, $actionArgs),
                            __('editor.submissionReview.editReview'),
                            'modal_add_file'
                        ),
                        __('common.edit'),
                        'edit'
                    ));
                    $this->addAction(new LinkAction(
                        'unassignReviewer',
                        new AjaxModal(
                            $router->url($request, null, null, 'unassignReviewer', null, $actionArgs),
                            $reviewAssignment->getDateConfirmed() ? __('editor.review.cancelReviewer') : __('editor.review.unassignReviewer'),
                            'modal_delete'
                        ),
                        $reviewAssignment->getDateConfirmed() ? __('editor.review.cancelReviewer') : __('editor.review.unassignReviewer'),
                        'delete'
                    ));
                } else {
                    $this->addAction(
                        new LinkAction(
                            'reinstateReviewer',
                            new AjaxModal(
                                $router->url($request, null, null, 'reinstateReviewer', null, $actionArgs),
                                __('editor.review.reinstateReviewer'),
                                'modal_add'
                            ),
                            __('editor.review.reinstateReviewer'),
                            'add'
                        )
                    );
                }
            }

            $this->addAction(
                new LinkAction(
                    'history',
                    new AjaxModal(
                        $router->url($request, null, null, 'reviewHistory', null, $actionArgs),
                        __('submission.history'),
                        'modal_information'
                    ),
                    __('submission.history'),
                    'more_info'
                )
            );

            $user = $request->getUser();
            if (
                !Validation::loggedInAs() &&
                $user->getId() != $reviewAssignment->getReviewerId() &&
                Validation::getAdministrationLevel($reviewAssignment->getReviewerId(), $user->getId()) === Validation::ADMINISTRATION_FULL &&
                !$reviewAssignment->getCancelled()
            ) {
                $dispatcher = $router->getDispatcher();
                $this->addAction(
                    new LinkAction(
                        'logInAs',
                        new RedirectConfirmationModal(
                            __('grid.user.confirmLogInAs'),
                            __('grid.action.logInAs'),
                            $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'login', 'signInAsUser', $reviewAssignment->getReviewerId())
                        ),
                        __('grid.action.logInAs'),
                        'enroll_user'
                    )
                );
            }

            // Add gossip action when appropriate
            $canCurrentUserGossip = Repo::user()->canCurrentUserGossip($reviewAssignment->getReviewerId());
            if ($canCurrentUserGossip) {
                $this->addAction(
                    new LinkAction(
                        'gossip',
                        new AjaxModal(
                            $router->url($request, null, null, 'gossip', null, $actionArgs),
                            __('user.gossip'),
                            'modal_information'
                        ),
                        __('user.gossip'),
                        'more_info'
                    )
                );
            }
        }
    }
}
