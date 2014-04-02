<?php

/**
 * @defgroup submission_common
 */

/**
 * @file classes/submission/common/PKPAction.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAction
 * @ingroup submission_common
 *
 * @brief Application-independent submission actions.
 */


class PKPAction {
	/**
	 * Constructor.
	 */
	function PKPAction() {

	}

	//
	// Actions.
	//
	/**
	 * Edit citations
	 * @param $request Request
	 * @param $submission Submission
	 * @return string the rendered response
	 */
	function editCitations(&$request, &$submission) {
		$router =& $request->getRouter();
		$dispatcher =& $this->getDispatcher();
		$templateMgr =& TemplateManager::getManager();

		// Add extra style sheets required for ajax components
		// FIXME: Must be removed after OMP->OJS backporting
		$templateMgr->addStyleSheet($request->getBaseUrl().'/styles/ojs.css');

		// Add extra java script required for ajax components
		// FIXME: Must be removed after OMP->OJS backporting
		$templateMgr->addJavaScript('lib/pkp/js/functions/modal.js');
		$templateMgr->addJavaScript('lib/pkp/js/lib/jquery/plugins/validate/jquery.validate.min.js');
		$templateMgr->addJavaScript('lib/pkp/js/functions/jqueryValidatorI18n.js');
		$templateMgr->addJavaScript('lib/pkp/js/lib/jquery/plugins/jquery.splitter.js');


		// Check whether the citation editor requirements are complete.
		// 1) PHP5 availability.
		$citationEditorConfigurationError = null;
		if (!checkPhpVersion('5.0.0')) {
			$citationEditorConfigurationError = 'submission.citations.editor.php5Required';
			$showIntroductoryMessage = false;
		} else {
			$showIntroductoryMessage = true;
		}
		$templateMgr->assign('showIntroductoryMessage', $showIntroductoryMessage);

		// 2) Citation editing must be enabled for the journal.
		if (!$citationEditorConfigurationError) {
			$context =& $router->getContext($request);
			if (!$context->getSetting('metaCitations')) $citationEditorConfigurationError = 'submission.citations.editor.pleaseSetup';
		}

		// 3) At least one citation parser is available.
		$citationDao =& DAORegistry::getDAO('CitationDAO'); // NB: This also loads the parser/lookup filter category constants.
		if (!$citationEditorConfigurationError) {
			$filterDao =& DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */
			$configuredCitationParsers =& $filterDao->getObjectsByGroup(CITATION_PARSER_FILTER_GROUP, $context->getId());
			if (!count($configuredCitationParsers)) $citationEditorConfigurationError = 'submission.citations.editor.pleaseAddParserFilter';
		}

		// 4) A citation output filter has been set.
		if (!$citationEditorConfigurationError && !($context->getSetting('metaCitationOutputFilterId') > 0)) {
			$citationEditorConfigurationError = 'submission.citations.editor.pleaseConfigureOutputStyle';
		}

		$templateMgr->assign('citationEditorConfigurationError', $citationEditorConfigurationError);

		// Should we display the "Introduction" tab?
		if (is_null($citationEditorConfigurationError)) {
			$user =& $request->getUser();
			$introductionHide = (boolean)$user->getSetting('citation-editor-hide-intro');
		} else {
			// Always show the introduction tab if we have a configuration error.
			$introductionHide = false;
		}
		$templateMgr->assign('introductionHide', $introductionHide);

		// Display an initial help message.
		$citations =& $citationDao->getObjectsByAssocId(ASSOC_TYPE_ARTICLE, $submission->getId());
		if ($citations->getCount() > 0) {
			$initialHelpMessage = __('submission.citations.editor.details.pleaseClickOnCitationToStartEditing');
		} else {
			$articleMetadataUrl = $router->url($request, null, null, 'viewMetadata', $submission->getId());
			$initialHelpMessage = __('submission.citations.editor.pleaseImportCitationsFirst', array('articleMetadataUrl' => $articleMetadataUrl));
		}
		$templateMgr->assign('initialHelpMessage', $initialHelpMessage);

		// Find out whether all citations have been processed or not.
		$unprocessedCitations =& $citationDao->getObjectsByAssocId(ASSOC_TYPE_ARTICLE, $submission->getId(), 0, CITATION_CHECKED);
		if ($unprocessedCitations->getCount() > 0) {
			$templateMgr->assign('unprocessedCitations', $unprocessedCitations->toArray());
		} else {
			$templateMgr->assign('unprocessedCitations', false);
		}

		// Add the grid URL.
		$citationGridUrl = $dispatcher->url($request, ROUTE_COMPONENT, null, 'grid.citation.CitationGridHandler', 'fetchGrid', null, array('assocId' => $submission->getId()));
		$templateMgr->assign('citationGridUrl', $citationGridUrl);

		// Add the export URL.
		$citationGridUrl = $dispatcher->url($request, ROUTE_COMPONENT, null, 'grid.citation.CitationGridHandler', 'exportCitations', null, array('assocId' => $submission->getId()));
		$templateMgr->assign('citationExportUrl', $citationGridUrl);

		// Add the submission.
		$templateMgr->assign_by_ref('submission', $submission);
	}
}

?>
