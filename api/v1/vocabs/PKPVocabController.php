<?php

/**
 * @file api/v1/vocabs/PKPVocabController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPVocabController
 *
 * @ingroup api_v1_vocab
 *
 * @brief Controller class to handle API requests for controlled vocab operations.
 *
 */

namespace PKP\API\v1\vocabs;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\controlledVocab\ControlledVocab;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\facades\Locale;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class PKPVocabController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'vocabs';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('', $this->getMany(...))->name('vocab.getMany');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get the controlled vocab entries available in this context
     *
     * @hook API::vocabs::getMany [[$vocab, &$entries, $illuminateRequest, response(), $request]]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        if (!$context) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $requestParams = $illuminateRequest->query();

        $vocab = $requestParams['vocab'] ?? '';
        $locale = $requestParams['locale'] ?? Locale::getLocale();
        $term = $requestParams['term'] ?? null;
        $locales = array_merge($context->getSupportedSubmissionMetadataLocales(), isset($requestParams['submissionId']) ? Repo::submission()->get((int) $requestParams['submissionId'])?->getPublicationLanguages() ?? [] : []);

        if (!in_array($locale, $locales)) {
            return response()->json([
                'error' => __('api.vocabs.400.localeNotSupported', ['locale' => $locale]),
            ], Response::HTTP_BAD_REQUEST);
        }

        if (ControlledVocab::hasDefinedVocabSymbolic($vocab)) {
            /** @var \PKP\controlledVocab\ControlledVocabEntryDAO $entryDao */
            $entryDao = Repo::controlledVocab()->getEntryDaoBySymbolic($vocab);
            $entries = $entryDao->getByContextId($vocab, $context->getId(), $locale, $term)->toArray();
        } else {
            $entries = [];
            Hook::call('API::vocabs::getMany', [$vocab, &$entries, $illuminateRequest, response(), $request]);
        }

        $data = [];
        foreach ($entries as $entry) {
            $data[] = $entry->getData($vocab, $locale);
        }

        $data = array_values(array_unique($data));

        return response()->json($data, Response::HTTP_OK);
    }
}
