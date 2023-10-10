<?php
/**
 * @file classes/components/fileAttachers/Upload.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Upload
 *
 * @ingroup classes_controllers_form
 *
 * @brief A class to compile initial state for a FileAttacherUpload component.
 */

namespace PKP\components\fileAttachers;

use APP\core\Application;
use PKP\context\Context;

class Upload extends BaseAttacher
{
    public string $component = 'FileAttacherUpload';
    public Context $context;

    /**
     * Initialize this file attacher
     *
     * @param string $label The label to display for this file attacher
     * @param string $description A description of this file attacher
     * @param string $button The label for the button to activate this file attacher
     */
    public function __construct(Context $context, string $label, string $description, string $button)
    {
        parent::__construct($label, $description, $button);
        $this->context = $context;
    }

    /**
     * Compile the props for this file attacher
     */
    public function getState(): array
    {
        $props = parent::getState();

        $request = Application::get()->getRequest();
        $props['temporaryFilesApiUrl'] = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $this->context->getData('urlPath'),
            'temporaryFiles'
        );
        $props['dropzoneOptions'] = [
            'maxFilesize' => Application::getIntMaxFileMBs(),
            'timeout' => ini_get('max_execution_time') ? ini_get('max_execution_time') * 1000 : 0,
            'dropzoneDictDefaultMessage' => __('form.dropzone.dictDefaultMessage'),
            'dropzoneDictFallbackMessage' => __('form.dropzone.dictFallbackMessage'),
            'dropzoneDictFallbackText' => __('form.dropzone.dictFallbackText'),
            'dropzoneDictFileTooBig' => __('form.dropzone.dictFileTooBig'),
            'dropzoneDictInvalidFileType' => __('form.dropzone.dictInvalidFileType'),
            'dropzoneDictResponseError' => __('form.dropzone.dictResponseError'),
            'dropzoneDictCancelUpload' => __('form.dropzone.dictCancelUpload'),
            'dropzoneDictUploadCanceled' => __('form.dropzone.dictUploadCanceled'),
            'dropzoneDictCancelUploadConfirmation' => __('form.dropzone.dictCancelUploadConfirmation'),
            'dropzoneDictRemoveFile' => __('form.dropzone.dictRemoveFile'),
            'dropzoneDictMaxFilesExceeded' => __('form.dropzone.dictMaxFilesExceeded'),
        ];
        $props['addFilesLabel'] = __('common.addFiles');
        $props['attachFilesLabel'] = __('common.attachFiles');
        $props['dragAndDropMessage'] = __('common.dragAndDropHere');
        $props['dragAndDropOrUploadMessage'] = __('common.orUploadFile');
        $props['backLabel'] = __('common.back');
        $props['removeItemLabel'] = __('common.removeItem');

        return $props;
    }
}
