<?php
/**
 * @file classes/components/form/FieldUpload.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldUpload
 * @ingroup classes_controllers_form
 *
 * @brief A field for uploading a file.
 */
namespace PKP\components\forms;
class FieldUpload extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-upload';

	/**
	 * @var array Options to pass to the dropzone.js instance.
	 *
	 * A `url` key must be included with the value of the API endpoint where files
	 *  can be uploaded to: <api-path>/temporaryFiles.
	 */
	public $options = [];

	/**
	 * @copydoc Field::__construct()
	 */
	public function __construct($name, $args = []) {
		parent::__construct($name, $args);

		$this->options['maxFilesize'] = \Application::getIntMaxFileMBs();
		$this->options['timeout'] = ini_get('max_execution_time')
			? ini_get('max_execution_time') * 1000
			: 0;

		$this->options = array_merge(
			[
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
			],
			$this->options
		);
	}

	/**
	 * @copydoc Field::validate()
	 */
	public function validate() {
		if (empty($this->options['url'])) {
			return false;
		}
		return parent::validate();
	}

	/**
	 * @copydoc Field::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		$config['options'] = $this->options;
		$config['uploadFileLabel'] = __('common.upload.addFile');
		$config['restoreLabel'] = __('common.upload.restore');

		return $config;
	}
}
