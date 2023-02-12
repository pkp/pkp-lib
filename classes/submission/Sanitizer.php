<?php

/**
 * @file classes/submission/Sanitizer.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Sanitizer
 *
 * @brief A sanitization class to sanitize submission data before saving
 */

 namespace PKP\submission;

use Exception;
use Illuminate\Support\Arr;
use PKP\config\Config;
use PKP\core\PKPString;

class Sanitizer 
{
    /**
     * Defined sanitization rule/method mapping to attributes
     * It's possible to have multiple sanitization rule for a single attributes
     * 
     * $sanitizeMap = [
     *   'attribute_1' => 'class_method_1',
     *   'attribute_2' => ['class_method_21', 'class_method_22'],
     *   ...
     * ]
     */
    protected array $sanitizeMap = [];

    /**
     * Passed params to sanitize
     */
    protected array $sanitizeParams;

    /**
     * Define if allow empty sanitization for attributes
     */
    protected bool $allowEmptySanization = false;

    /**
     * The entity code to number conversion to update
     * As TinyMCE do a force entity conversion even when defined the 'entity_encoding' as 'raw'
     * @see 5.0+ : https://www.tiny.cloud/docs/configure/content-filtering/#entity_encoding
     * @see 6.0+ : https://www.tiny.cloud/docs/tinymce/6/content-filtering/#entity_encoding
     */
    protected array $entityCodeToCharMapping = [
        '&' => '&amp;',
        '>' => '&gt;',
        '<' => '&lt;',
        '"' => '&quot;',
        "'" => '&apos;',
    ];

    /**
     * Apply the sanitization process for given attribute
     */
    protected function runSanitizationProcess(string $method, mixed $paramKey, mixed $beforeSanitizevalue): void
    {
        $this->sanitizeParams = array_merge($this->sanitizeParams, [
            $paramKey => $this->{$method}($beforeSanitizevalue),
        ]);
    }

    /**
     * Sanitize the submission localized/unlocalized title[title] attribute
     */
    public function title(string|array $param): string|array
    {
        $allowedTagList = array_map(
            'trim', 
            explode(',', Config::getVar('security', 'allowed_title_html', 'b,i,u,sup,sub'))
        );

        if(is_array($param)) {
            foreach($param as $localeKey => $localizedSubmissionTitle) {
                // Can not use HTMLPurifier though stripUnsafeHtml as it's convery specials
                // e.g '&' turned into '&amp;'
                $param[$localeKey] = str_replace(
                    array_values($this->entityCodeToCharMapping),
                    array_keys($this->entityCodeToCharMapping),
                    strip_tags($localizedSubmissionTitle, $allowedTagList)
                );
            }

            return $param;
        }

        return str_replace(
            array_values($this->entityCodeToCharMapping),
            array_keys($this->entityCodeToCharMapping),
            strip_tags($param, $allowedTagList)
        );
    }

    /**
     * Sanitize the submission localized/unlocalized sub title[subtitle] attribute
     */
    public function subtitle(string|array $param): string|array
    {
        return $this->title($param);
    }

    /**
     * Define should allow attributes with empty sanitization rules
     */
    public function allowEmptySanitization(): self
    {
        $this->allowEmptySanization = true;

        return $this;
    }
    
    /**
     * Run sanitization
     */
    public function sanitize(array $params, array $sanitizingKeys = []): array
    {
        $this->sanitizeParams = $params;

        $sanitizableParams = empty($sanitizingKeys) 
            ? $params 
            : array_intersect_key($params, array_flip($sanitizingKeys));
        
        foreach($sanitizableParams as $paramKey => $paramValue) {
            if(in_array($paramKey, $this->sanitizeMap)) {
                collect(Arr::wrap($this->sanitizeMap[$paramKey]))
                    ->each(
                        fn($method) => $this->runSanitizationProcess(
                            $method, 
                            $paramKey, 
                            $paramValue
                        )
                    );
                
                continue;
            }

            if (method_exists($this, $paramKey)) {
                $this->runSanitizationProcess($paramKey, $paramKey, $paramValue);
                continue;
            }

            if (!$this->allowEmptySanization) {
                throw new Exception(
                    sprintf("Running empty sanitization for attribute '%s' is now allowed", $paramKey)
                );
            }
        }

        return $this->sanitizeParams;
    }
}