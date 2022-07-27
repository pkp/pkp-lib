<?php
/**
 * @file classes/components/fileAttachers/Library.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Library
 * @ingroup classes_controllers_form
 *
 * @brief A class to compile initial state for a FileAttacherLibrary component.
 */

namespace PKP\components\fileAttachers;

use APP\core\Application;
use APP\submission\Submission;
use PKP\context\Context;

class Library extends BaseAttacher
{
    public string $component = 'FileAttacherLibrary';
    public Context $context;
    public Submission $submission;

    /**
     * Initialize this file attacher
     *
     */
    public function __construct(Context $context, ?Submission $submission = null)
    {
        parent::__construct(
            __('email.addAttachment.libraryFiles'),
            __('email.addAttachment.libraryFiles.description'),
            __('email.addAttachment.libraryFiles.attach')
        );
        $this->context = $context;
        $this->submission = $submission;
    }

    /**
     * Compile the props for this file attacher
     */
    public function getState(): array
    {
        $props = parent::getState();

        $request = Application::get()->getRequest();
        $props['libraryApiUrl'] = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $this->context->getData('urlPath'),
            '_library'
        );
        if ($this->submission) {
            $props['includeSubmissionId'] = $this->submission->getId();
        }
        $props['attachSelectedLabel'] = __('common.attachSelected');
        $props['backLabel'] = __('common.back');
        $props['downloadLabel'] = __('common.download');

        return $props;
    }
}
