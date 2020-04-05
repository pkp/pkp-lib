<?php
/**
 * @file classes/components/form/FieldPubId.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldPubId
 * @ingroup classes_controllers_form
 *
 * @brief A field for generating a pub id, like a DOI.
 */
namespace PKP\components\forms;
class FieldPubId extends Field {
	/** @copydoc Field::$component */
	public $component = 'field-pub-id';

	/** @var string The journal/press initials to use when generating a pub id */
	public $contextInitials;

	/** @var boolean If a %p in the pattern should stand for press (OMP). Otherwise it means pages (OJS). */
	public $isPForPress = false;

	/** @var string The issue number to use when generating a pub id */
	public $issueNumber;

	/** @var string The issue volume to use when generating a pub id */
	public $issueVolume;

	/** @var string The page numbers use when generating a pub id */
	public $pages;

	/** @var string The pattern to use when generating a pub id */
	public $pattern;

	/** @var string The pub id prefix for this context */
	public $prefix;

	/** @var string The publisher id to use when generating a pub id */
	public $publisherId;

	/** @var string Optional separator to add between prefix and suffix when generating pub id */
	public $separator = '';

	/** @var string The submission ID to use when generating a pub id */
	public $submissionId;

	/** @var string The publication ID to use when generating a pub id */
	public $publicationId;

	/** @var string The year of publication to use when generating a pub id */
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
		if (isset($this->publicationId)) {
			$config['publicationId'] = $this->publicationId;
		}
		if (isset($this->year)) {
			$config['year'] = $this->year;
		}
		$config['isPForPress'] = $this->isPForPress;
		$config['separator'] = $this->separator;

		return $config;
	}
}
