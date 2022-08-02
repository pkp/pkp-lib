<?php
/**
 * @file classes/components/fileAttachers/FileStage.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileStage
 * @ingroup classes_controllers_form
 *
 * @brief A class to compile initial state for a FileAttacherFileStage component.
 */

namespace PKP\components\fileAttachers;

use APP\core\Application;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\submission\reviewRound\ReviewRound;

class FileStage extends BaseAttacher
{
    public string $component = 'FileAttacherFileStage';
    public Context $context;
    public Submission $submission;
    public array $fileStages = [];

    /**
     * Initialize a file stage attacher
     *
     * @param string $label The label to display for this file attacher
     * @param string $description A description of this file attacher
     * @param string $button The label for the button to activate this file attacher
     */
    public function __construct(Context $context, Submission $submission, string $label, string $description, string $button)
    {
        parent::__construct($label, $description, $button);
        $this->context = $context;
        $this->submission = $submission;
    }

    /**
     * Add a submission file stage that can be used for attachments
     */
    public function withFileStage(int $fileStage, string $label, ?ReviewRound $reviewRound = null): self
    {
        $queryParams = ['fileStages' => [$fileStage]];
        if ($reviewRound) {
            $queryParams['reviewRoundIds'] = [$reviewRound->getId()];
        }
        $this->fileStages[] = [
            'label' => $label,
            'queryParams' => $queryParams,
        ];
        return $this;
    }

    /**
     * Compile the props for this file attacher
     */
    public function getState(): array
    {
        $props = parent::getState();

        $request = Application::get()->getRequest();
        $props['submissionFilesApiUrl'] = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $this->context->getData('urlPath'),
            'submissions/' . $this->submission->getId() . '/files'
        );

        $props['fileStages'] = $this->fileStages;
        $props['attachSelectedLabel'] = __('common.attachSelected');
        $props['downloadLabel'] = __('common.download');
        $props['backLabel'] = __('common.back');

        return $props;
    }
}
