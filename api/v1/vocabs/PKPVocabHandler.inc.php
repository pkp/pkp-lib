<?php

/**
 * @file api/v1/vocabs/PKPVocabHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPVocabHandler
 * @ingroup api_v1_vocab
 *
 * @brief Handle API requests for controlled vocab operations.
 *
 */

use Stringy\Stringy;

import('lib.pkp.classes.handler.APIHandler');
import('classes.core.Services');

class PKPVocabHandler extends APIHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_handlerPath = 'vocabs';
		$this->_endpoints = [
			'GET' => [
				[
					'pattern' => $this->getEndpointPattern(),
					'handler' => [$this, 'getMany'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR],
				],
			],
		];
		parent::__construct();
	}

	//
	// Implement methods from PKPHandler
	//
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Get the controlled vocab entries available in this context
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function getMany($slimRequest, $response, $args) {
		$request = Application::get()->getRequest();
		$context = $request->getContext();

		if (!$context) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		$requestParams = $slimRequest->getQueryParams();

		$vocab = $requestParams['vocab'] ?? '';
		$locale = $requestParams['locale'] ?? AppLocale::getLocale();
		$term = $requestParams['term'] ?? null;

		if (!in_array($locale, $context->getData('supportedSubmissionLocales'))) {
			return $response->withStatus(400)->withJsonError('api.vocabs.400.localeNotSupported', ['locale' => $locale]);
		}

		// Load constants
		DAORegistry::getDAO('SubmissionKeywordDAO');
		DAORegistry::getDAO('SubmissionSubjectDAO');
		DAORegistry::getDAO('SubmissionDisciplineDAO');
		DAORegistry::getDAO('SubmissionLanguageDAO');
		DAORegistry::getDAO('SubmissionAgencyDAO');

		switch ($vocab) {
			case CONTROLLED_VOCAB_SUBMISSION_KEYWORD:
				$submissionKeywordEntryDao = DAORegistry::getDAO('SubmissionKeywordEntryDAO'); /** @var SubmissionKeywordEntryDAO $submissionKeywordEntryDao */
				$entries = $submissionKeywordEntryDao->getByContextId($vocab, $context->getId(), $locale, $term)->toArray();
				break;
			case CONTROLLED_VOCAB_SUBMISSION_SUBJECT:
				$submissionSubjectEntryDao = DAORegistry::getDAO('SubmissionSubjectEntryDAO'); /** @var SubmissionSubjectEntryDAO $submissionSubjectEntryDao */
				$entries = $submissionSubjectEntryDao->getByContextId($vocab, $context->getId(), $locale, $term)->toArray();
				break;
			case CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE:
				$submissionDisciplineEntryDao = DAORegistry::getDAO('SubmissionDisciplineEntryDAO'); /** @var SubmissionDisciplineEntryDAO $submissionDisciplineEntryDao */
				$entries = $submissionDisciplineEntryDao->getByContextId($vocab, $context->getId(), $locale, $term)->toArray();
				break;
			case CONTROLLED_VOCAB_SUBMISSION_LANGUAGE:
				$isoCodes = new \Sokil\IsoCodes\IsoCodesFactory();
				$words = array_filter(PKPString::regexp_split('/\s+/', $term), 'strlen');
				$languageNames = [];
				foreach ($isoCodes->getLanguages(\Sokil\IsoCodes\IsoCodesFactory::OPTIMISATION_IO) as $language) {
					if ($language->getAlpha2() && $language->getType() === 'L' && $language->getScope() === 'I' && Stringy::create($language->getLocalName())->containsAny($words, false)) {
						$languageNames[] = $language->getLocalName();
					}
				}
				asort($languageNames);
				return $response->withJson($languageNames, 200);
			case CONTROLLED_VOCAB_SUBMISSION_AGENCY:
				$submissionAgencyEntryDao = DAORegistry::getDAO('SubmissionAgencyEntryDAO'); /* @var $submissionAgencyEntryDao SubmissionAgencyEntryDAO */
				$entries = $submissionAgencyEntryDao->getByContextId($vocab, $context->getId(), $locale, $term)->toArray();
				break;
			default:
				$entries = [];
				\HookRegistry::call('API::vocabs::getMany', [$vocab, &$entries, $slimRequest, $response, $this->request]);
		}

		$data = [];
		foreach ($entries as $entry) {
			$data[] = $entry->getData($vocab, $locale);
		}

		$data = array_values(array_unique($data));

		return $response->withJson($data, 200);
	}
}
