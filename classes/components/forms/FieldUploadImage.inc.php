<?php
/**
 * @file classes/components/form/FieldUploadImage.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldUploadImage
 * @ingroup classes_controllers_form
 *
 * @brief A field for uploading a file.
 */
namespace PKP\components\forms;
class FieldUploadImage extends FieldUpload {
	/** @copydoc Field::$component */
	public $component = 'field-upload-image';

	/** @var string Base url for displaying the image */
	public $baseUrl = '';

	/**
	 * @copydoc Field::__construct()
	 */
	public function __construct($name, $args = []) {
		parent::__construct($name, $args);
		$this->i18n = array_merge([
			'thumbnailDescription' => __('common.upload.thumbnailPreview'),
			'altTextLabel' => __('common.altText'),
			'altTextDescription' => __('common.altTextInstructions'),
		], $this->i18n);
	}

	/**
	 * @copydoc Field::getConfig()
	 */
	public function getConfig() {
		if (!array_key_exists('acceptedFiles', $this->options)) {
			$this->options['acceptedFiles'] = 'image/*';
		}
		$config = parent::getConfig();
		$config['baseUrl'] = $this->baseUrl;

		return $config;
	}

	/**
	 * @copydoc Field::getEmptyValue()
	 */
	public function getEmptyValue() {
		return null;
	}
}
