<?php
/**
 * @file controllers/form/context/PKPSearchIndexingForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSearchIndexingForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring a context's search indexing settings.
 */
namespace PKP\components\forms\context;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldText;
use \PKP\components\forms\FieldTextarea;

define('FORM_SEARCH_INDEXING', 'searchIndexing');

class PKPSearchIndexingForm extends FormComponent {
	/** @copydoc FormComponent::$id */
	public $id = FORM_SEARCH_INDEXING;

	/** @copydoc FormComponent::$method */
	public $method = 'PUT';

	/**
	 * Constructor
	 *
	 * @param $action string URL to submit the form to
	 * @param $locales array Supported locales
	 * @param $context Context Journal or Press to change settings for
	 * @param $sitemapUrl string A URL to the context's sitemap for use in the
	 *  search engine indexing group description
	 */
	public function __construct($action, $locales, $context, $sitemapUrl) {
		$this->action = $action;
		$this->successMessage = __('manager.setup.searchEngineIndexing.success');
		$this->locales = $locales;

		$this->addGroup([
				'id' => 'search',
				'label' => __('manager.setup.searchEngineIndexing'),
				'description' => __('manager.setup.searchEngineIndexing.description', ['sitemapUrl' => $sitemapUrl]),
			])
			->addField(new FieldText('searchDescription', [
				'label' => __('common.description'),
				'tooltip' => __('manager.setup.searchDescription.description'),
				'isMultilingual' => true,
				'value' => $context->getData('searchDescription'),
				'groupId' => 'search',
			]))
			->addField(new FieldTextArea('customHeaders', [
				'label' => __('manager.distribution.customHeaders'),
				'tooltip' => __('manager.distribution.customHeaders.description'),
				'isMultilingual' => true,
				'value' => $context->getData('customHeaders'),
				'groupId' => 'search',
			]));
	}
}
