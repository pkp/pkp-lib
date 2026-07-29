<?php
/**
 * @defgroup controllers_modals_review_linkAction Submission Metadata Link Actions
 */
/**
 * @file controllers/modals/review/ReviewerViewMetadataLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerViewMetadataLinkAction
 *
 * @ingroup controllers_modals_review_linkAction
 *
 * @brief An action to open the submission metadata modal.
 */

namespace PKP\controllers\modals\review;

use APP\core\Request;
use APP\facades\Repo;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\VueModal;
use PKP\publication\PKPPublication;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewerViewMetadataLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param int $submissionId
     * @param int $reviewAssignmentId
     */
    public function __construct($request, $submissionId, $reviewAssignmentId)
    {
        parent::__construct(
            'viewMetadata',
            new VueModal(
                'ReviewerSubmissionDetailsModal',
                $this->getModalProps($request, $submissionId, $reviewAssignmentId)
            ),
            __('reviewer.step1.viewAllDetails')
        );
    }

    /**
     * Collect the submission details that the reviewer may see
     */
    protected function getModalProps($request, int $submissionId, int $reviewAssignmentId): array
    {
        $submission = Repo::submission()->get($submissionId);
        $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignmentId);
        $publication = $submission->getCurrentPublication();

        $props = [
            'title' => $publication->getLocalizedFullTitle(null, 'html'),
            'abstract' => $publication->getLocalizedData('abstract'),
            'keywords' => $this->getControlledVocabNames($publication, 'keywords'),
            'subjects' => $this->getControlledVocabNames($publication, 'subjects'),
            'disciplines' => $this->getControlledVocabNames($publication, 'disciplines'),
            'authors' => null,
            'dataAvailability' => null,
            'fundingStatement' => null,
            'dataCitations' => [],
        ];

        if ($reviewAssignment->getReviewMethod() == ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS) {
            return $props;
        }

        // For open or anonymous/disclosed author review, we can show the authors and other metadata
        $props['authors'] = $publication->getAuthorString();
        $props['dataAvailability'] = $publication->getLocalizedData('dataAvailability');
        $props['fundingStatement'] = $publication->getLocalizedData('fundingStatement');

        if ($request->getContext()->getData('dataCitations')) {
            $props['dataCitations'] = collect($publication->getData('dataCitations') ?? [])
                ->map(fn ($dataCitation) => [
                    'id' => $dataCitation->id,
                    'title' => $dataCitation->title,
                    'identifierType' => $dataCitation->identifierType,
                    'url' => $dataCitation->url,
                ])
                ->values()
                ->all();
        }

        return $props;
    }

    /**
     * Map a controlled vocabulary to an array of values - the stored entries are ['name' => ...], not plain strings
     */
    protected function getControlledVocabNames(PKPPublication $publication, string $metadataKey): array
    {
        return collect($publication->getLocalizedData($metadataKey) ?: [])->pluck('name')->all();
    }
}
