<?php
/**
 * @file classes/components/fileAttachers/ReviewFiles.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFiles
 *
 * @ingroup classes_controllers_form
 *
 * @brief A class to compile initial state for a FileAttacherReviewFiles component.
 */

namespace PKP\components\fileAttachers;

use APP\core\Application;
use APP\facades\Repo;
use Exception;
use PKP\context\Context;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submissionFile\SubmissionFile;

class ReviewFiles extends BaseAttacher
{
    public string $component = 'FileAttacherReviewFiles';
    public Context $context;

    /** @var iterable<SubmissionFile> $files */
    public iterable $files;

    /** @var array<ReviewAssignment> $reviewAssignments */
    public array $reviewAssignments;

    /**
     * Initialize this file attacher
     *
     * @param string $label The label to display for this file attacher
     * @param string $description A description of this file attacher
     * @param string $button The label for the button to activate this file attacher
     */
    public function __construct(string $label, string $description, string $button, iterable $files, array $reviewAssignments, Context $context)
    {
        parent::__construct($label, $description, $button);
        $this->files = $files;
        $this->reviewAssignments = $reviewAssignments;
        $this->context = $context;
    }

    /**
     * Compile the props for this file attacher
     */
    public function getState(): array
    {
        $props = parent::getState();
        $props['attachSelectedLabel'] = __('common.attachSelected');
        $props['backLabel'] = __('common.back');
        $props['downloadLabel'] = __('common.download');
        $props['files'] = $this->getFilesState();

        return $props;
    }

    protected function getFilesState(): array
    {
        $request = Application::get()->getRequest();

        $files = [];
        /** @var SubmissionFile $file */
        foreach ($this->files as $file) {
            if (!isset($this->reviewAssignments[$file->getData('assocId')])) {
                throw new Exception('Tried to add review file attachment from unknown review assignment.');
            }
            $files[] = [
                'id' => $file->getId(),
                'name' => $file->getData('name'),
                'documentType' => app()->get('file')->getDocumentType($file->getData('documentType')),
                'reviewerName' => $this->reviewAssignments[$file->getData('assocId')]->getReviewerFullName(),
                'url' => $request->getDispatcher()->url(
                    $request,
                    Application::ROUTE_COMPONENT,
                    $this->context->getData('urlPath'),
                    'api.file.FileApiHandler',
                    'downloadFile',
                    null,
                    [
                        'submissionFileId' => $file->getId(),
                        'submissionId' => $file->getData('submissionId'),
                        'stageId' => Repo::submissionFile()->getWorkflowStageId($file),
                    ]
                ),
            ];
        }

        return $files;
    }
}
