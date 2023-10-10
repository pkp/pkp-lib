<?php

/**
 * @file api/v1/vocabs/PKPVocabHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPVocabHandler
 *
 * @ingroup api_v1_vocab
 *
 * @brief Handle API requests for controlled vocab operations.
 *
 */

namespace PKP\API\v1\vocabs;

use APP\core\Application;
use PKP\core\APIResponse;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\submission\SubmissionAgencyDAO;
use PKP\submission\SubmissionDisciplineDAO;
use PKP\submission\SubmissionKeywordDAO;
use PKP\submission\SubmissionLanguageDAO;
use PKP\submission\SubmissionSubjectDAO;
use Slim\Http\Request;
use Stringy\Stringy;

class PKPVocabHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'vocabs';
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
                ],
            ],
        ];
        parent::__construct();
    }

    //
    // Implement methods from PKPHandler
    //
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get the controlled vocab entries available in this context
     */
    public function getMany(Request $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $requestParams = $slimRequest->getQueryParams();

        $vocab = $requestParams['vocab'] ?? '';
        $locale = $requestParams['locale'] ?? Locale::getLocale();
        $term = $requestParams['term'] ?? null;

        if (!in_array($locale, $context->getData('supportedSubmissionLocales'))) {
            return $response->withStatus(400)->withJsonError('api.vocabs.400.localeNotSupported', ['locale' => $locale]);
        }

        switch ($vocab) {
            case SubmissionKeywordDAO::CONTROLLED_VOCAB_SUBMISSION_KEYWORD:
                $submissionKeywordEntryDao = DAORegistry::getDAO('SubmissionKeywordEntryDAO'); /** @var \PKP\submission\SubmissionKeywordEntryDAO $submissionKeywordEntryDao */
                $entries = $submissionKeywordEntryDao->getByContextId($vocab, $context->getId(), $locale, $term)->toArray();
                break;
            case SubmissionSubjectDAO::CONTROLLED_VOCAB_SUBMISSION_SUBJECT:
                $submissionSubjectEntryDao = DAORegistry::getDAO('SubmissionSubjectEntryDAO'); /** @var \PKP\submission\SubmissionSubjectEntryDAO $submissionSubjectEntryDao */
                $entries = $submissionSubjectEntryDao->getByContextId($vocab, $context->getId(), $locale, $term)->toArray();
                break;
            case SubmissionDisciplineDAO::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE:
                $submissionDisciplineEntryDao = DAORegistry::getDAO('SubmissionDisciplineEntryDAO'); /** @var \PKP\submission\SubmissionDisciplineEntryDAO $submissionDisciplineEntryDao */
                $entries = $submissionDisciplineEntryDao->getByContextId($vocab, $context->getId(), $locale, $term)->toArray();
                break;
            case SubmissionLanguageDAO::CONTROLLED_VOCAB_SUBMISSION_LANGUAGE:
                $words = array_filter(PKPString::regexp_split('/\s+/', $term), 'strlen');
                $languageNames = [];
                foreach (Locale::getLanguages() as $language) {
                    if ($language->getAlpha2() && $language->getType() === 'L' && $language->getScope() === 'I' && Stringy::create($language->getLocalName())->containsAny($words, false)) {
                        $languageNames[] = $language->getLocalName();
                    }
                }
                asort($languageNames);
                return $response->withJson($languageNames, 200);
            case SubmissionAgencyDAO::CONTROLLED_VOCAB_SUBMISSION_AGENCY:
                $submissionAgencyEntryDao = DAORegistry::getDAO('SubmissionAgencyEntryDAO'); /** @var \PKP\submission\SubmissionAgencyEntryDAO $submissionAgencyEntryDao */
                $entries = $submissionAgencyEntryDao->getByContextId($vocab, $context->getId(), $locale, $term)->toArray();
                break;
            default:
                $entries = [];
                Hook::call('API::vocabs::getMany', [$vocab, &$entries, $slimRequest, $response, $request]);
        }

        $data = [];
        foreach ($entries as $entry) {
            $data[] = $entry->getData($vocab, $locale);
        }

        $data = array_values(array_unique($data));

        return $response->withJson($data, 200);
    }
}
