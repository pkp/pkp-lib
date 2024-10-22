<?php

/**
 * @file api/v1/reviews/PKPReviewController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewController
 *
 * @ingroup api_v1_reviews
 *
 * @brief Handle API requests for reviews operations.
 *
 */

namespace PKP\API\v1\reviews;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use DOMImplementation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Mpdf\Mpdf;
use PKP\core\PKPApplication;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\file\TemporaryFileManager;
use PKP\log\EmailLogEntry;
use PKP\log\SubmissionEmailLogEventType;
use PKP\reviewForm\ReviewFormElement;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\reviewForm\ReviewFormResponseDAO;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\submission\SubmissionCommentDAO;
use PKP\submissionFile\SubmissionFile;

class PKPReviewController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'reviews';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     *
     * @throws \Exception
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_REVIEWER,
            ]),
        ];
    }

    /**
     * @throws \Exception
     */
    public function getGroupRoutes(): void
    {
        Route::get('history/{submissionId}/{reviewRoundId}', $this->getHistory(...))
            ->name('review.get.submission.round.history')
            ->whereNumber(['reviewRoundId', 'submissionId']);

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_REVIEWER,
                Role::ROLE_ID_AUTHOR,
            ]),
        ])->group(function () {
            Route::get('{submissionId}/{reviewAssignmentId}/export-pdf', $this->exportReviewPDF(...))
                ->name('review.export.pdf')
                ->whereNumber(['reviewAssignmentId', 'submissionId']);

            Route::get('{submissionId}/{reviewAssignmentId}/export-xml', $this->exportReviewXML(...))
                ->name('review.export.xml')
                ->whereNumber(['reviewAssignmentId', 'submissionId']);

            Route::get('{submissionId}/exports/{fileId}', $this->getExportedFile(...))
                ->name('review.export.getFile')
                ->whereNumber(['submissionId', 'fileId']);
        });
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId', true));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get reviewer's submission round history
     *
     * @throws \Exception
     */
    public function getHistory(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();

        $reviewerId = $request->getUser()->getId();
        $submissionId = $illuminateRequest->route('submissionId');
        $reviewRoundId = $illuminateRequest->route('reviewRoundId');

        $reviewAssignment = Repo::reviewAssignment()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewerIds([$reviewerId])
            ->filterByReviewRoundIds([$reviewRoundId])
            ->getMany()
            ->first();

        if (!$reviewAssignment) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $submission = Repo::submission()->get($submissionId, $contextId);
        $publication = $submission->getCurrentPublication();
        $publicationTitle = $publication->getData('title');

        $publicationType = null;
        if ($submission->getSectionId()) {
            $section = Repo::section()->get($submission->getSectionId());
            $publicationType = $section->getData('title');
        }

        $publicationAbstract = $publication->getData('abstract');
        $publicationKeywords = $publication->getData('keywords');

        $declineEmail = null;
        if ($reviewAssignment->getDeclined()) {
            $emailLogs = EmailLogEntry::withAssocId($submissionId)
                ->withEventTypes([SubmissionEmailLogEventType::REVIEW_DECLINE])
                ->withSenderId($reviewerId)
                ->withAssocType(Application::ASSOC_TYPE_SUBMISSION)
                ->get();

            foreach ($emailLogs as $emailLog) {
                $dateSent = substr($emailLog->dateSent, 0, 10);
                $dateConfirmed = substr($reviewAssignment->getData('dateConfirmed'), 0, 10);
                // Compare the dates to get the decline email associated to the current round.
                if ($dateSent === $dateConfirmed) {
                    $declineEmail = [
                        'subject' => $emailLog->subject,
                        'body' => $emailLog->body,
                    ];
                    break;
                }
            }
        }

        $reviewAssignmentProps = Repo::reviewAssignment()->getSchemaMap()->map($reviewAssignment, $submission);
        // It doesn't seem we can translate the recommendation inside the vue page as it's a dynamic label key.
        $recommendation = $reviewAssignment->getLocalizedRecommendation();

        $reviewAssignmentId = $reviewAssignment->getId();
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
        $allSubmissionComments = $submissionCommentDao->getReviewerCommentsByReviewerId($submissionId, $reviewerId, $reviewAssignmentId)->toArray();
        $viewableComments = [];
        $privateComments = [];
        foreach ($allSubmissionComments as $submissionComment) {
            $comments = $submissionComment->getData('comments');
            if ($submissionComment->getData('viewable')) {
                $viewableComments[] = $comments;
            } else {
                $privateComments[] = $comments;
            }
        }

        $genreDao = DAORegistry::getDAO('GenreDAO');
        $fileGenres = $genreDao->getByContextId($contextId)->toArray();

        $attachments = Repo::submissionFile()->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewRoundIds([$reviewRoundId])
            ->filterByUploaderUserIds([$reviewerId])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT])
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT, [$reviewAssignmentId])
            ->getMany();

        $attachmentsProps = Repo::submissionFile()
            ->getSchemaMap()
            ->mapMany($attachments, $fileGenres)
            ->toArray();

        $stageId = $reviewAssignment->getStageId();
        $lastReviewAssignment = Repo::reviewAssignment()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewerIds([$reviewerId])
            ->filterByStageId($stageId)
            ->filterByLastReviewRound(true)
            ->getMany()
            ->first();

        $filesProps = [];
        if ($lastReviewAssignment && $lastReviewAssignment->getDeclined() != 1) {
            $files = Repo::submissionFile()->getCollector()
                ->filterBySubmissionIds([$submissionId])
                ->filterByReviewRoundIds([$reviewRoundId])
                ->filterByAssoc(PKPApplication::ASSOC_TYPE_REVIEW_ROUND, [$reviewAssignmentId])
                ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_REVIEW_FILE])
                ->getMany();

            $filesProps = Repo::submissionFile()
                ->getSchemaMap()
                ->mapMany($files, $fileGenres)
                ->toArray();
        }

        $reviewRoundHistory = [
            'publicationTitle' => $publicationTitle,
            'publicationType' => $publicationType,
            'publicationAbstract' => $publicationAbstract,
            'publicationKeywords' => $publicationKeywords,
            'declineEmail' => $declineEmail,
            'reviewAssignment' => $reviewAssignmentProps,
            'recommendation' => $recommendation,
            'comments' => $viewableComments,
            'privateComments' => $privateComments,
            'attachments' => array_values($attachmentsProps),
            'files' => array_values($filesProps),
        ];

        return response()->json($reviewRoundHistory, Response::HTTP_OK);
    }

    /**
     * Creates a review as PDF
     */
    protected function generatePDF(bool $authorFriendly): int
    {
        $request = $this->getRequest();
        $reviewId = $request->getUserVar('reviewAssignmentId');
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /* @var $submissionCommentDao SubmissionCommentDAO */
        $reviewAssignment = Repo::reviewAssignment()->get($reviewId);
        $submissionId = $submission->getId();
        $submissionComments = $submissionCommentDao->getReviewerCommentsByReviewerId($submissionId, $reviewAssignment->getReviewerId(), $reviewId, true);
        $submissionCommentsPrivate = $submissionCommentDao->getReviewerCommentsByReviewerId($submissionId, $reviewAssignment->getReviewerId(), $reviewId, false);
        $title = $submission->getCurrentPublication()->getLocalizedTitle(null, 'html');
        $cleanTitle = str_replace("&nbsp;", " ", strip_tags($title));
        $mpdf = new Mpdf([
            'default_font' => 'NotoSansSC',
            'mode' => '+aCJK',
            "autoScriptToLang" => true,
            "autoLangToFont" => true,
        ]);

        if($authorFriendly) {
            $reviewAssignments = Repo::reviewAssignment()->getCollector()->filterBySubmissionIds([$submissionId])->getMany();
            $alphabet = range('A', 'Z');
            $reviewerLetter = "";
            $i = 0;
            foreach($reviewAssignments as $submissionReviewAssignment) {
                if($reviewAssignment->getReviewerId() === $submissionReviewAssignment->getReviewerId()) {
                    $reviewerLetter = $alphabet[$i];
                }
                $i++;
            }
            $reviewerName = __('user.role.reviewer') . ": $reviewerLetter";
        } else {
            $reviewerName = __('user.role.reviewer') . ": " .  $reviewAssignment->getReviewerFullName();
        }

        $html = "
            <html>
            <head>
                <style>
                    body { font-family: Arial; color: rgb(41, 41, 41); }
                    h1, h2, h3, h4, h5, h6 { margin: 0; padding: 5px 0; }
                    .section { margin-bottom: 15px; }
                </style>
            </head>
            <body>
                <div class='section'>
                    <h2>" . __('editor.review') . ": $cleanTitle</h2>
                </div>
                <div class='section'>
                    <h3 style='font-weight: bold;'>" . $reviewerName . "</h3>
                </div>
        ";

        if ($dateCompleted = $reviewAssignment->getDateCompleted()) {
            $html .= "
                <div class='section'>
                   <h4 style='font-weight: bold;'>" . __('common.completed') . ': ' . $dateCompleted . "</h4>
                </div>
            ";
        }

        if ($reviewAssignment->getRecommendation()) {
            $recommendation = $reviewAssignment->getLocalizedRecommendation();
            $html .= "
                <div class='section'>
                    <h4 style='font-weight: bold;'>" . __('editor.submission.recommendation') . ': ' . $recommendation . "</h4>
                </div>
            ";
        }

        if ($reviewAssignment->getReviewFormId()) {
            $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
            /* @var $reviewFormElementDao ReviewFormElementDAO */
            $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');
            /* @var $reviewFormResponseDao ReviewFormResponseDAO */
            $reviewFormResponses = $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewAssignment->getId());
            $reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewAssignment->getReviewFormId());
            while ($reviewFormElement = $reviewFormElements->next()) {
                if ($authorFriendly && !$reviewFormElement->getIncluded()) continue;
                $elementId = $reviewFormElement->getId();
                $html .= "
                    <div>
                        <h4 style='font-weight: bold;'>" . strip_tags($reviewFormElement->getLocalizedQuestion()) . "</h4>
                    </div>
                ";
                if($description = $reviewFormElement->getLocalizedDescription()) {
                    $html .= "<span>" . $description . "</span>";
                }
                $value = $reviewFormResponses[$elementId];
                $textFields = [
                    ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD,
                    ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD,
                    ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXTAREA
                ];
                if (in_array($reviewFormElement->getElementType(), $textFields)) {
                    $html .= "<div class='section'><span>" . $value . "</span></div>";
                } elseif ($reviewFormElement->getElementType() == ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES) {
                    $possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
                    $reviewFormCheckboxResponses = $reviewFormResponses[$elementId];
                    foreach ($possibleResponses as $key => $possibleResponse) {
                        if (in_array($key, $reviewFormCheckboxResponses)) {
                            $html .= "
                                <div style='margin-bottom: 5px;'>
                                    <input type='checkbox' checked=1>
                                    <span>
                                        " . htmlspecialchars($possibleResponse) . "
                                    </span>
                                </div>
                            ";
                        } else {
                            $html .= "
                                <div style='margin-bottom: 5px;'>
                                    <input type='checkbox'>
                                    <span>
                                        " . htmlspecialchars($possibleResponse) . "
                                    </span>
                                </div>
                            ";
                        }
                    }
                    $html .= "<div class='section'></div>";
                } elseif ($reviewFormElement->getElementType() == ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS) {
                    $possibleResponsesRadios = $reviewFormElement->getLocalizedPossibleResponses();
                    foreach ($possibleResponsesRadios as $key => $possibleResponseRadio) {
                        if($reviewFormResponses[$elementId] == $key) {
                            $html .= "
                                <div style='margin-bottom: 5px;'>
                                    <input type='radio' checked='1'>
                                    <span>
                                        " . htmlspecialchars($possibleResponseRadio) . "
                                    </span>
                                </div>
                            ";
                        } else {
                            $html .= "
                                <div style='margin-bottom: 5px;'>
                                    <input type='radio'>
                                    <span>
                                        " . htmlspecialchars($possibleResponseRadio) . "
                                    </span>
                                </div>
                            ";
                        }
                    }
                    $html .= "<div class='section'></div>";
                } elseif ($reviewFormElement->getElementType() == ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX) {
                    $possibleResponsesDropdown = $reviewFormElement->getLocalizedPossibleResponses();
                    $dropdownResponse = $possibleResponsesDropdown[$reviewFormResponses[$elementId]];
                    $html .= "<div class='section'><span>" . $dropdownResponse . "</span></div>";
                }
            }
        } else {
            $html .= "
                <div>
                    <h4 style='font-weight: bold;'>" . __('editor.review.reviewerComments') . "</h4>
                    <em style='font-weight: bold; color:#606060;'>" . __('submission.comments.forAuthorEditor') . "</em>
                </div>
            ";

            if($submissionComments->records->isEmpty()) $html .= "<div class='section'><span>" . __('common.none') . "</span></div>";
            foreach ($submissionComments->records as $comment) {
                $commentStripped = strip_tags($comment->comments);
                $html .= "<div class='section'><span>" . $commentStripped . "</span></div>";
            }
            if (!$authorFriendly) {
                $html .= "
                    <div>
                        <em style='font-weight: bold; color:#606060;'>" . __('submission.comments.cannotShareWithAuthor') . "</em>
                    </div>
                ";

                if($submissionCommentsPrivate->records->isEmpty()) $html .= "<div class='section'><span>" . __('common.none') . "</span></div>";
                foreach ($submissionCommentsPrivate->records as $comment) {
                    $commentStripped = strip_tags($comment->comments);
                    $html .= "<div class='section'><span>" . $commentStripped . "</span></div>";
                }
            }
        }
        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_SUBMISSION])
            ->getMany();

        $primaryLocale = Locale::getPrimaryLocale();
        $html .= "<div><h4 style='font-weight: bold;'>" . __('reviewer.submission.reviewFiles') . "</h4></div>";

        foreach ($submissionFiles as $submissionFile) {
            $fileName = $submissionFile->_data['name'][$primaryLocale];
            $html .= "<div class='section'><span>" . $fileName . "</span></div>";
        }

        $html .= "</body></html>";
        $mpdf->WriteHTML($html);

        $exportFileName = "submission_review_{$submissionId}-{$reviewId}.pdf";
        $pdfContent = $mpdf->Output($exportFileName, 'S');
        $fileManager = new TemporaryFileManager();
        $tempFilename = $fileManager->getBasePath() . $exportFileName;
        $fileManager->writeFile($tempFilename, $pdfContent);
        $user = Application::get()->getRequest()->getUser();

        return $fileManager->createTempFileFromExisting($tempFilename, $user->getId());
    }

    /**
     * Export a review as PDF to temporary file
     */
    public function exportReviewPDF(Request $illuminateRequest): JsonResponse
    {
        $this->validateReviewExport($illuminateRequest);
        $authorFriendly = (bool) $illuminateRequest->authorFriendly;
        $temporaryFileId = $this->generatePDF($authorFriendly);

        return response()->json([
            'temporaryFileId' => $temporaryFileId
        ], Response::HTTP_OK);
    }

    protected function generateXML(bool $authorFriendly): int
    {
        $request = $this->getRequest();
        $reviewId = $request->getUserVar('reviewAssignmentId');
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $submission->getCurrentPublication();
        $htmlTitle = $publication->getLocalizedTitle(null, 'html');
        $articleTitle = PKPString::mapTitleHtmlTagsToXml($htmlTitle);
        $reviewAssignment = Repo::reviewAssignment()->get($reviewId);
        $submissionId = $submission->getId();
        $recommendation = $reviewAssignment->getLocalizedRecommendation();
        $impl = new DOMImplementation();
        $doctype = $impl->createDocumentType('article',
            '-//NLM//DTD JATS (Z39.96) Journal Archiving and Interchange DTD v1.2 20190208//EN',
            'JATS-archivearticle1.dtd');

        $xml = $impl->createDocument(null, '', $doctype);
        $xml->encoding = 'UTF-8';
        $article = $xml->createElement('article');
        $article->setAttribute('article-type', 'reviewer-report');
        $article->setAttribute('dtd-version', '1.2');
        $article->setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        $xml->appendChild($article);

        $front = $xml->createElement('front');
        $article->appendChild($front);

        $journalMeta = $xml->createElement('journal-meta');

        $baseUrl = $request->getBaseUrl();
        $selfUri = $xml->createElement('self-uri', $baseUrl);
        $journalMeta->appendChild($selfUri);

        $front->appendChild($journalMeta);
        $articleMeta = $xml->createElement('article-meta');
        $front->appendChild($articleMeta);

        $articleId = $xml->createElement('article-id', $submissionId);
        $articleId->setAttribute('id-type', 'submission-id');
        $articleMeta->appendChild($articleId);

        $titleGroup = $xml->createElement('title-group');
        $articleMeta->appendChild($titleGroup);
        $articleTitle = $xml->createElement('article-title', $articleTitle);
        $titleGroup->appendChild($articleTitle);

        $contribGroup = $xml->createElement('contrib-group');
        $articleMeta->appendChild($contribGroup);

        $contrib = $xml->createElement('contrib');
        $contrib->setAttribute('contrib-type', 'author');
        $contribGroup->appendChild($contrib);

        if($authorFriendly) {
            $reviewAssignments = Repo::reviewAssignment()->getCollector()->filterBySubmissionIds([$submissionId])->getMany();
            $alphabet = range('A', 'Z');
            $reviewerLetter = "";
            $i = 0;
            foreach($reviewAssignments as $submissionReviewAssignment) {
                if($reviewAssignment->getReviewerId() === $submissionReviewAssignment->getReviewerId()) {
                    $reviewerLetter = $alphabet[$i];
                }
                $i++;
            }
            $reviewerName = __('user.role.reviewer') . ": $reviewerLetter";
            $anonymous = $xml->createElement('anonymous');
            $contrib->appendChild($anonymous);
        } else {
            $reviewerName = __('user.role.reviewer') . ": " .  $reviewAssignment->getReviewerFullName();
        }

        $role = $xml->createElement('role', $reviewerName);
        $role->setAttribute('specific-use', 'reviewer');
        $contrib->appendChild($role);

        $pubHistory = $xml->createElement('pub-history');
        $event = $xml->createElement('event');
        $event->setAttribute('event-type', 'current-submission-review-completed');

        $dateReviewCompleted = $reviewAssignment->getDateCompleted();
        $dateParsed = Carbon::parse($dateReviewCompleted);

        $eventDesc = $xml->createElement('event-desc', 'Current Submission Review Completed');
        $eventDate = $xml->createElement('date');
        $eventDate->setAttribute('iso-8601-date', $dateReviewCompleted);

        $event->appendChild($eventDesc);
        $day = $xml->createElement('day', $dateParsed->day);
        $eventDate->appendChild($day);
        $month = $xml->createElement('month', $dateParsed->month);
        $eventDate->appendChild($month);
        $year = $xml->createElement('year', $dateParsed->year);
        $eventDate->appendChild($year);
        $event->appendChild($eventDate);
        $pubHistory->appendChild($event);
        $articleMeta->append($pubHistory);

        $permissions = $xml->createElement('permissions');
        $articleMeta->appendChild($permissions);

        $licenseRef = $xml->createElement('ali:license_ref', 'http://creativecommons.org/licenses/by/4.0/');
        $permissions->appendChild($licenseRef);

        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /* @var $submissionCommentDao SubmissionCommentDAO */
        $submissionComments = $submissionCommentDao->getReviewerCommentsByReviewerId($submissionId, $reviewAssignment->getReviewerId(), $reviewId, true);

        $customMetaGroupObject = $xml->createElement('custom-meta-group');
        $customMetaPeerReviewStage = $xml->createElement('custom-meta');
        $peerReviewStageTag = $xml->createElement('meta-name', 'peer-review-stage');
        $peerReviewStageValueTag = $xml->createElement('meta-value', 'pre-publication');

        $customMetaPeerReviewStage->appendChild($peerReviewStageTag);
        $customMetaPeerReviewStage->appendChild($peerReviewStageValueTag);

        $customMetaReccomObject = $xml->createElement('custom-meta');
        $recomTag = $xml->createElement('meta-name', 'peer-review-recommendation');
        $recomValueTag = $xml->createElement('meta-value', $recommendation);

        $customMetaReccomObject->appendChild($recomTag);
        $customMetaReccomObject->appendChild($recomValueTag);

        if ($reviewAssignment->getReviewFormId()) {
            $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
            /* @var $reviewFormElementDao ReviewFormElementDAO */
            $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');
            /* @var $reviewFormResponseDao ReviewFormResponseDAO */
            $reviewFormResponses = $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewAssignment->getId());
            $reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewAssignment->getReviewFormId());
            while ($reviewFormElement = $reviewFormElements->next()) {
                if ($authorFriendly && !$reviewFormElement->getIncluded()) continue;
                $elementId = $reviewFormElement->getId();
                if ($reviewFormElement->getElementType() == ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES) {
                    $results = [];
                    foreach ($reviewFormResponses[$elementId] as $index) {
                        if (isset($reviewFormElement->getLocalizedPossibleResponses()[$index])) {
                            $results[] = $reviewFormElement->getLocalizedPossibleResponses()[$index];
                        }
                    }
                    $answer = implode(', ', $results);
                } elseif (in_array($reviewFormElement->getElementType(), [ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS, ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX])) {
                    $possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
                    $answer = array_key_exists($reviewFormResponses[$elementId], $possibleResponses) ? $possibleResponses[$reviewFormResponses[$elementId]] : '';
                } else {
                    $answer = $reviewFormResponses[$elementId];
                }
                $customMetaObject = $xml->createElement('custom-meta');
                $nameTag = $xml->createElement('meta-name', strip_tags($reviewFormElement->getLocalizedQuestion()));
                $valueTag = $xml->createElement('meta-value', $answer);
                $customMetaObject->appendChild($nameTag);
                $customMetaObject->appendChild($valueTag);
                $customMetaGroupObject->appendChild($customMetaObject);
            }
        } else {
            foreach ($submissionComments->records as $key => $comment) {
                $customMetaCommentsObject = $xml->createElement('custom-meta');
                $metaName = $submissionComments->records->count() > 1 ? 'submission-comments-' . $key + 1 : 'submission-comments';
                $commentsTag = $xml->createElement('meta-name', $metaName);
                $commentsValueTag = $xml->createElement('meta-value', strip_tags($comment->comments));
                $customMetaCommentsObject->appendChild($commentsTag);
                $customMetaCommentsObject->appendChild($commentsValueTag);
                $customMetaGroupObject->appendChild($customMetaCommentsObject);
            }

            if(!$authorFriendly) {
                $submissionCommentsPrivate = $submissionCommentDao->getReviewerCommentsByReviewerId($submissionId, $reviewAssignment->getReviewerId(), $reviewId, false);
                foreach ($submissionCommentsPrivate->records as $key => $comment) {
                    $customMetaCommentsPrivateObject = $xml->createElement('custom-meta');
                    $metaName = $submissionCommentsPrivate->records->count() > 1 ? 'submission-comments-private-' . $key + 1 : 'submission-comments-private';
                    $commentsTag = $xml->createElement('meta-name', $metaName);
                    $commentsValueTag = $xml->createElement('meta-value', strip_tags($comment->comments));
                    $customMetaCommentsPrivateObject->appendChild($commentsTag);
                    $customMetaCommentsPrivateObject->appendChild($commentsValueTag);
                    $customMetaGroupObject->appendChild($customMetaCommentsPrivateObject);
                }
            }
        }
        $customMetaGroupObject->appendChild($customMetaPeerReviewStage);
        $customMetaGroupObject->appendChild($customMetaReccomObject);
        $articleMeta->appendChild($customMetaGroupObject);
        $fileManager = new TemporaryFileManager();
        $tempFilename = $fileManager->getBasePath() . "submission_review_{$submissionId}-{$reviewId}.xml";
        $xml->save($tempFilename);
        $user = Application::get()->getRequest()->getUser();

        return $fileManager->createTempFileFromExisting($tempFilename, $user->getId());
    }

    protected function validateReviewExport(Request $illuminateRequest) {
        if(!in_array($illuminateRequest->authorFriendly, ['0', '1'])) {
            return response()->json([
                'error' => __('api.400.invalidAuthorFriendlyParameter')
            ], Response::HTTP_BAD_REQUEST);
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if (!$submission) {
            return response()->json([
                'error' => __('api.404.resourceNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        $request = $this->getRequest();
        $submissionId = $request->getUserVar('submissionId');
        $reviewId = $request->getUserVar('reviewAssignmentId');

        $contextId = $request->getContext()->getId();
        $submissionReviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([$submissionId])
            ->filterByContextIds([$contextId])
            ->getMany();

        if(!$submissionReviewAssignments->first()
            || $submissionReviewAssignments->first()->getData('submissionId') != $submissionId
            || $submissionReviewAssignments->first()->getData('id') != $reviewId)
        {
            return response()->json([
                'error' => __('api.404.resourceNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

    }

    /**
     * Export a review as XML
     */
    public function exportReviewXML(Request $illuminateRequest): JsonResponse
    {
        $this->validateReviewExport($illuminateRequest);
        $authorFriendly = (bool) $illuminateRequest->authorFriendly;
        $temporaryFileId = $this->generateXML($authorFriendly);

        return response()->json([
            'temporaryFileId' => $temporaryFileId
        ], Response::HTTP_OK);
    }

    /**
     * Download exported review file from temporary file ID
     */
    public function getExportedFile(Request $illuminateRequest): JsonResponse
    {
        $fileId = $illuminateRequest->route('fileId');
        $currentUser = Application::get()->getRequest()->getUser();
        $tempFileManager = new TemporaryFileManager();
        $isSuccess = $tempFileManager->downloadById($fileId, $currentUser->getId());

        if (!$isSuccess) {
            return response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }
        return response()->json([], Response::HTTP_OK);
    }
}
