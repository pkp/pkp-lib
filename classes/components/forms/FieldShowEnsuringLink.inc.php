<?php
/**
 * @file classes/components/form/FieldShowEnsuringLink.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldShowEnsuringLink
 * @ingroup classes_controllers_form
 *
 * @brief An extension of the FieldOptions for the configuration setting which
 *  determines whether or not to show a link to reviewers about keeping reviews
 *  anonymous.
 */
namespace PKP\components\forms;
class FieldShowEnsuringLink extends FieldOptions {
	/** @copydoc Field::$component */
	public $component = 'field-show-ensuring-link';

	/** @var string The message to show in a modal when the link is clicked.  */
	public $message = '';

	/**
	 * @copydoc Field::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		$config['message'] = __('review.anonymousPeerReview');
		$config['modalTitle'] = __('review.anonymousPeerReview.title');

		return $config;
	}
}
