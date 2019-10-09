<?php
/**
 * @file classes/components/form/FieldDoi.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FieldDoi
 * @ingroup classes_controllers_form
 *
 * @brief A field for generating a DOI.
 */
namespace PKP\components\forms;
class FieldDoi extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-doi';

	/** @var string The journal/press initials to use when generating a DOI */
	public $contextInitials;

	/** @var string The issue number to use when generating a DOI */
	public $issueNumber;

	/** @var string The issue volume to use when generating a DOI */
	public $issueVolume;

	/** @var string The page numbers use when generating a DOI */
	public $pages;

	/** @var string The pattern to use when generating a DOI */
	public $pattern;

	/** @var string The DOI prefix for this context */
	public $prefix;

	/** @var string The publisher id to use when generating a DOI */
	public $publisherId;

	/** @var string The submission ID to use when generating a DOI */
	public $submissionId;

	/** @var string The year of publication to use when generating a DOI */
	public $year;

	/**
	 * @copydoc Field::getConfig()
	 */
	public function getConfig() {
		$config = parent::getConfig();
		if (isset($this->contextInitials)) {
			$config['contextInitials'] = $this->contextInitials;
		}
		if (isset($this->issueNumber)) {
			$config['issueNumber'] = $this->issueNumber;
		}
		if (isset($this->issueVolume)) {
			$config['issueVolume'] = $this->issueVolume;
		}
		if (isset($this->pages)) {
			$config['pages'] = $this->pages;
		}
		if (isset($this->pattern)) {
			$config['pattern'] = $this->pattern;
		}
		if (isset($this->prefix)) {
			$config['prefix'] = $this->prefix;
		}
		if (isset($this->publisherId)) {
			$config['publisherId'] = $this->publisherId;
		}
		if (isset($this->submissionId)) {
			$config['submissionId'] = $this->submissionId;
		}
		if (isset($this->year)) {
			$config['year'] = $this->year;
		}

		return $config;
	}
}
