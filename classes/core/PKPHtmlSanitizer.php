<?php

/**
 * @file classes/core/PKPHtmlSanitizer.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
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
     * Instance of HtmlSanitizer
     */
    protected HtmlSanitizer $htmlSanitizer;

    /**
     * Create a new instance
     * 
     * @param string $allowable String of allowed tags with attribites generated in same
     *                          structure as the security.[allowed_html/allowed_title_html]
     */
    public function __construct(string $allowable)
    {
        $this->w3cValidElements = collect(
            array_merge(W3CReference::HEAD_ELEMENTS, W3CReference::BODY_ELEMENTS)
        )->keys();

        $this->htmlSanitizer = new HtmlSanitizer(
            $this->buildSanitizerConfig(
                $this->generateAllowedTagToAttributeMap(
                    $allowable
                )
            )
        );
    }

    /**
     * Sanitize the given html string
     */
    public function sanitize(string $html): string
    {   
        return $this->htmlSanitizer->sanitize(
            /**
             * Here we are removing any html tags that should not be handled by sanitizer
             * as defined in the \Symfony\Component\HtmlSanitizer\Reference\W3CReference::HEAD_ELEMENTS
             * and \Symfony\Component\HtmlSanitizer\Reference\W3CReference::BODY_ELEMENTS as combined.
             */
            strip_tags($html, $this->getSanitizableTags()->toArray())
        );
    }

    /**
     * Build up the \Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig instance
     * 
     * @param Collection $allowedTagToAttributeMap  See the @return docblock for 
     *                                              PKPHtmlSanitizer::generateAllowedTagToAttributeMap()
     */
    protected function buildSanitizerConfig(Collection $allowedTagToAttributeMap): HtmlSanitizerConfig
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

        return $this->htmlSanitizerConfig;
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
     * 
     * @param   string $allowable   Allowded tag to attribute map as the
     *                              structure define in config keys such as 
     *                              security.[allowed_html/allowed_title_html]
     *                              
     * @return  Collection          Collection of allowed tags to allowed attributes map as key/value.
     *                              In collection each tag will be mapped to an array that may 
     *                              contain allowed attributes or it can be empty which define that
     *                              no attribute is allowed for that tag, structure such as 
     *                              [
     *                                  HTML_TAG_1 => [ALLOWED_ATTRIBUTE_FOR_HTML_TAG_1, ...],
     *                                  HTML_TAG_2 => [],
     *                                  ...
     *                              ]
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
