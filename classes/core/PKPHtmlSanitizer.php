<?php

/**
 * @file classes/core/PKPHtmlSanitizer.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPHtmlSanitizer
 *
 * @ingroup core
 *
 * @brief Wrapper on the top of Symfony's HtmlSanitizer implementation
 *
 */

namespace PKP\core;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use PKP\config\Config;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Reference\W3CReference;

class PKPHtmlSanitizer
{
    /**
     * Collection of valid element specify by W3C Sanitizer API
     * 
     * @see const HEAD_ELEMENTS and BODY_ELEMENTS at \Symfony\Component\HtmlSanitizer\Reference\W3CReference
     * @see https://wicg.github.io/sanitizer-api/#default-configuration
     */
    protected Collection $w3cValidElements;

    /**
     * Instance of HtmlSanitizerConfig config
     */
    protected HtmlSanitizerConfig $htmlSanitizerConfig;

    /**
     * Create a new instance
     */
    public function __construct(string $allowable)
    {
        $this->w3cValidElements = collect(
            array_merge(W3CReference::HEAD_ELEMENTS, W3CReference::BODY_ELEMENTS)
        )->keys();

        $this->buildSanitizerConfig(
            $this->generateAllowedTagToAttributeMap(
                Config::getVar('security', $allowable, null) ?? $allowable
            )
        );
    }

    /**
     * Set a pre defined instance of \Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig
     */
    public function setSanitizerConfig(HtmlSanitizerConfig $htmlSanitizerConfig): self
    {
        $this->htmlSanitizerConfig = $htmlSanitizerConfig;

        return $this;
    }

    /**
     * Get the instance of \Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig
     */
    public function getSanitizerConfig(): HtmlSanitizerConfig
    {
        return $this->htmlSanitizerConfig;
    }

    /**
     * Sanitize the given html string
     */
    public function sanitize(string $html): string
    {   
        return (new HtmlSanitizer($this->htmlSanitizerConfig))->sanitize(
            // Here we are removing any html tags that should not be handled by sanitizer
            strip_tags($html, $this->getSanitizableTags()->toArray())
        );
    }

    /**
     * Build up the \Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig instance
     */
    protected function buildSanitizerConfig(Collection $allowedTagToAttributeMap): void
    {
        $this->htmlSanitizerConfig = (new HtmlSanitizerConfig())
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowMediaSchemes(['https', 'http']);
        
        if ($allowedTagToAttributeMap->count()) {
            $allowedTagToAttributeMap->each(
                fn (array $attributes, string $tag) => $this->htmlSanitizerConfig = $this->htmlSanitizerConfig->allowElement($tag, $attributes)
            );
        }

        $this->getNonAllowedHtmlTags()->each(
            fn (string $tag) => $this->htmlSanitizerConfig = $this->htmlSanitizerConfig->blockElement($tag)
        );
    }

    /**
     * Get the collection of non allowed tags for the given configuration
     */
    protected function getNonAllowedHtmlTags(): Collection
    {
        return $this->w3cValidElements
            ->diff(collect($this->htmlSanitizerConfig->getAllowedElements())->keys());
    }

    /**
     * Get the collection of tags that will not be stripped/removed by php's "strip_tags" function
     * but rather should be handled by the sanitizer class
     */
    protected function getSanitizableTags(): Collection
    {
        return $this->w3cValidElements
            ->merge(collect($this->htmlSanitizerConfig->getAllowedElements())->keys())
            ->merge(collect([ // list of dangerous tags that should be only handled by sanitization library
                'script',
            ]))
            ->unique();
    }

    /**
     * Generate the collection of allowed tags to allowed attributes map as key/value[array] structure
     */
    protected function generateAllowedTagToAttributeMap(string $allowable): Collection
    {
        return Str::of($allowable)
            ->explode(',')
            ->mapWithKeys(function(string $allowedTagWithAttr) {
                
                // Extract the tag itself (e.g. div, p, a ...)
                preg_match('/\[[^][]+]\K|\w+/', $allowedTagWithAttr, $matches);
                $allowedTag = collect($matches)->first();

                // Extract the attributes associated with tag (e.g. class, href ...)
                preg_match("/\[([^\]]*)\]/", $allowedTagWithAttr, $matches);
                $allowedAttributes = collect($matches)->last();

                if($allowedTag) {
                    return [
                        $allowedTag => Str::of($allowedAttributes)
                            ->explode('|')
                            ->filter()
                            ->toArray()
                    ];
                }
        
                return [];
            });
    }
}
