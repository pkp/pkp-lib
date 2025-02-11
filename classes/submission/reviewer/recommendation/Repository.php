<?php

/**
 * @file lib/pkp/classes/submission/reviewer/recommendation/Repository.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to manage actions related to reviewer recommendation
 */

namespace PKP\submission\reviewer\recommendation;

use Illuminate\Support\Arr;
use Exception;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;
use PKP\context\Context;
use PKP\facades\Locale;

class Repository
{
    /**
     * Get default recommendation seed data mapped as value => localeKey
     */
    public function getDefaultRecommendations(): array
    {
        return [
            1 => 'reviewer.article.decision.accept', // SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT
            2 => 'reviewer.article.decision.pendingRevisions', // SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS
            3 => 'reviewer.article.decision.resubmitHere', // SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE
            4 => 'reviewer.article.decision.resubmitElsewhere', // SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE
            5 => 'reviewer.article.decision.decline', // SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE
            6 => 'reviewer.article.decision.seeComments', // SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS
        ];
    }

    /**
     * Add the default recommendations to context
     */
    public function addDefaultRecommendations(Context $context): void
    {
        $defaultRecommendations = $this->getDefaultRecommendations();

        $defaultRecommendationsExists = ReviewerRecommendation::query()
            ->withContextId($context->getId())
            ->withRecommendations(array_keys($defaultRecommendations))
            ->exists();
        
        if ($defaultRecommendationsExists) {
            throw new Exception('Few or all default recommendations already added for context');
        }

        $locales = array_merge(
            Arr::wrap($context->getData('primaryLocale')),
            $context->getData('supportedLocales')
        );

        collect($defaultRecommendations)
            ->each (
                fn (string $translatableKey, int $recommendationValue) => ReviewerRecommendation::create([
                    'contextId' => $context->getId(),
                    'value' => $recommendationValue,
                    'status' => 1,
                    'title' => collect($locales)
                        ->mapWithKeys(
                            fn (string $locale): array => [
                                $locale => Locale::get($translatableKey, [], $locale)
                            ]
                        )
                        ->toArray(),
                ])
            );
    }

    /**
     * Add new localized translation for default recommendations on new locale addition to context
     */
    public function setLocalizedDataOnNewLocaleAdd(Context $context, string $localeToAdd, ?string $localeToCompare = null): void
    {
        $localeToCompare ??= $context->getPrimaryLocale();
        $defaultRecommendations = $this->getDefaultRecommendations();
        $suggestions = ReviewerRecommendation::query()
            ->withContextId($context->getId())
            ->withRecommendations(array_keys($defaultRecommendations))
            ->get();

        foreach ($suggestions as $suggestion) {
            
            // If the locale to add translation of `title` already exists, nothing to add and continue
            if (isset($suggestion->title[$localeToAdd])) {
                continue;
            }

            // If the locale to compare translation of `title` does not exists, nothing to compare with and continue
            if (!isset($suggestion->title[$localeToCompare])) {
                continue;
            }

            $localeToCompareTranslation = $suggestion->title[$localeToCompare];
            $localeKey = $defaultRecommendations[$suggestion->value];

            // if the locale to compare stored as title locale is not same as retrived translation from system's default local
            // it has been changed from the default translation 
            // so we will not allow to add new locale translation and contine
            if ($localeToCompareTranslation !== Locale::get($localeKey, [], $localeToCompare)) {
                continue;
            }

            $localeToAddTranslation = Locale::get($defaultRecommendations[$suggestion->value], [], $localeToAdd);

            $title = $suggestion->title;
            $title[$localeToAdd] = $localeToAddTranslation;
            $suggestion->update(['title' => $title]);
        }
    }
}
