<?php

/**
 * @file classes/submission/traits/HasWordCountValidation.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasWordCountValidation
 *
 * @brief A trait to validate the word count of a localized data
 */

namespace PKP\submission\traits;

use PKP\context\Context;
use PKP\core\PKPString;
use APP\submission\Submission;

trait HasWordCountValidation
{
    /**
     * Validate the word count of a publication localized data
     */
    protected function validateWordCount(
        Context $context,
        Submission $submission,
        int $wordLimit,
        string $errorMessageKey,
        array $localizedData = [],
    ): array
    {
        $errors = [];
        
        if (empty($localizedData)) {
            return $errors;
        }

        $allowedLocales = $submission->getPublicationLanguages(
            $context->getSupportedSubmissionMetadataLocales()
        );

        foreach ($allowedLocales as $localeKey) {
            $propValue = $localizedData[$localeKey] ?? null;
            $wordCount = $propValue ? PKPString::getWordCount($propValue) : 0;
            if ($wordCount > $wordLimit) {
                $errors[$localeKey] = [
                    __($errorMessageKey,[
                        'limit' => $wordLimit,
                        'count' => $wordCount
                    ])
                ];
            }
        }

        return $errors;
    }
}
