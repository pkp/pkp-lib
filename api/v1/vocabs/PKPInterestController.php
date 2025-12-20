<?php

/**
 * @file api/v1/vocabs/PKPInterestController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInterestController
 *
 * @ingroup api_v1_vocabs
 *
 * @brief Controller class to handle API requests for user interests vocabulary.
 * This is a public endpoint with no authentication required.
 */

namespace PKP\API\v1\vocabs;

use APP\core\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\controlledVocab\ControlledVocabEntry;
use PKP\controlledVocab\ControlledVocabEntryMatch;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\user\interest\UserInterest;

class PKPInterestController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'vocabs/interests';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        // No authentication required - publicly accessible endpoint
        return [];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('', $this->getMany(...))->name('interest.getMany');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        // No authorization required for public endpoint
        return true;
    }

    /**
     * Get a collection of interests (controlled vocab entries)
     *
     * This endpoint returns site-wide user interests, which are shared across
     * the entire application and not bound to any specific context.
     *
     * Interests are not multilingual and are stored with an empty locale.
     *
     * @param \Illuminate\Http\Request $illuminateRequest
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $requestParams = $illuminateRequest->query();

        // Get the search term if provided
        $term = $requestParams['term'] ?? null;

        // Query controlled vocab entries for interests (site-wide, not multilingual)
        $entries = ControlledVocabEntry::query()
            ->whereHas(
                'controlledVocab',
                fn ($query) => $query
                    ->withSymbolics([UserInterest::CONTROLLED_VOCAB_INTEREST])
                    ->withAssoc(Application::ASSOC_TYPE_SITE, Application::SITE_CONTEXT_ID)
            )
            ->withLocales(['']) // Interests are stored with empty locale
            ->when(
                $term,
                fn ($query) => $query->withSetting('name', $term, ControlledVocabEntryMatch::PARTIAL)
            )
            ->get();

        // Transform entries to match the vocabs endpoint format
        // For interests (stored with empty locale), extract string values from multilingual arrays
        $data = collect($entries)
            ->map(function (ControlledVocabEntry $entry): array {
                $entryData = $entry->getEntryData('');
                // Flatten any multilingual properties by extracting the first value
                return collect($entryData)
                    ->map(fn ($value) => is_array($value) ? collect($value)->first() : $value)
                    ->toArray();
            })
            ->unique(fn (array $entryData): string =>
                ($entryData[ControlledVocabEntry::CONTROLLED_VOCAB_ENTRY_IDENTIFIER] ?? '') .
                ($entryData[ControlledVocabEntry::CONTROLLED_VOCAB_ENTRY_SOURCE] ?? '') .
                ($entryData['name'] ?? '')
            )
            ->values()
            ->toArray();

        return response()->json($data, Response::HTTP_OK);
    }
}
