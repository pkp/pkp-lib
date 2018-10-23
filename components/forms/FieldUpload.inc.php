<?php
/**
 * @file controllers/form/FieldUpload.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FieldUpload
 * @ingroup classes_controllers_form
 *
 * @brief A field for uploading a file.
 */
import ('lib.pkp.components.forms.Field');
class FieldUpload extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-upload';

	/**
	 * @var string Options to pass to the dropzone.js instance.
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
		$this->i18n = array_merge([
			'uploadFile' => __('common.upload.addFile'),
			'remove' => __('common.remove'),
			'restore' => __('common.upload.restore'),
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
		], $this->i18n);
	}

	/**
	 * :copydoc Field::validate()
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
		$this->options['maxFilesize'] = Application::getIntMaxFileMBs();
		$config['options'] = $this->options;

		return $config;
	}
}
