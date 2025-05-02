<?php

/**
 * @file classes/submission/reviewer/recommendation/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to manage actions related to reviewer recommendation
 */

namespace PKP\submission\reviewer\recommendation;

use APP\core\Application;
use Illuminate\Support\Arr;
use Exception;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;
use PKP\submission\reviewer\recommendation\RecommendationOption;
use PKP\submission\reviewAssignment\ReviewAssignment;

class Repository
{
    /**
     * Get default recommendation seed data mapped as value => localeKey
     */
    public function getDefaultRecommendations(): array
    {
        return [
            'reviewer.article.decision.accept', // SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT
            'reviewer.article.decision.pendingRevisions', // SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS
            'reviewer.article.decision.resubmitHere', // SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE
            'reviewer.article.decision.resubmitElsewhere', // SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE
            'reviewer.article.decision.decline', // SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE
            'reviewer.article.decision.seeComments', // SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS
        ];
    }

    /**
     * Add the default recommendations to context
     */
    public function addDefaultRecommendations(Context $context): void
    {
        $defaultRecommendationsExists = ReviewerRecommendation::query()
            ->withContextId($context->getId())
            ->withDefaultRecommendationsOnly()
            ->exists();
        
        if ($defaultRecommendationsExists) {
            throw new Exception('Some or all default recommendations already added for context');
        }

        $defaultRecommendations = $this->getDefaultRecommendations();

        $locales = array_merge(
            Arr::wrap($context->getData('primaryLocale')),
            $context->getData('supportedLocales')
        );

        collect($defaultRecommendations)
            ->each (
                fn (string $translatableKey) => ReviewerRecommendation::create([
                    'contextId' => $context->getId(),
                    'status' => 1,
                    'defaultTranslationKey' => $translatableKey,
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
        if (!Application::get()->hasCustomizableReviewerRecommendation()) {
            return;
        }

        $localeToCompare ??= $context->getPrimaryLocale();

        $suggestions = ReviewerRecommendation::query()
            ->withContextId($context->getId())
            ->withDefaultRecommendationsOnly()
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
            $localeKey = $suggestion->{ReviewerRecommendation::DEFAULT_RECOMMENDATION_TRANSLATION_KEY};

            // if the locale to compare stored as title locale is not same as retrived translation from system's default local
            // it has been changed from the default translation 
            // so we will not allow to add new locale translation and contine
            if ($localeToCompareTranslation !== Locale::get($localeKey, [], $localeToCompare)) {
                continue;
            }

            $localeToAddTranslation = Locale::get($localeKey, [], $localeToAdd);

            $title = $suggestion->title;
            $title[$localeToAdd] = $localeToAddTranslation;
            $suggestion->update(['title' => $title]);
        }
    }

    /**
     * Get an associative array matching reviewer recommendation id mapped to localized title.
     *
     * @return array recommendation => localizedTitle
     */
    public function getRecommendationOptions(
        Context $context,
        RecommendationOption $active = RecommendationOption::ACTIVE,
        ?ReviewAssignment $reviewAssignment = null,
        ?string $locale = null
    ): array
    {
        static $reviewerRecommendationOptions = [];
        
        if (!Application::get()->hasCustomizableReviewerRecommendation()) {
            return [];
        }
        
        if (!empty($reviewerRecommendationOptions)) {
            return $reviewerRecommendationOptions;
        }

        return $reviewerRecommendationOptions = ReviewerRecommendation::query()
            ->withContextId($context->getId())
            ->withActive($active)
            ->when(
                $reviewAssignment && $reviewAssignment->getData('reviewerRecommendationId'),
                fn ($query) => $query->orWhere(
                    fn ($query) => $query->withRecommendations(
                        [$reviewAssignment->getData('reviewerRecommendationId')]
                    )
                )
            )
            ->get()
            ->mapWithKeys(
                fn (ReviewerRecommendation $recommendation): array => [
                    $recommendation->id => $recommendation->getLocalizedData('title', $locale)
                ]
            )
            ->toArray();
    }
}
