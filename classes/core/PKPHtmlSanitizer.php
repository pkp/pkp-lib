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

use DOMDocument;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use PKP\config\Config;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class PKPHtmlSanitizer
{
    /**
     * Collection of allowed tags to allowed attributes map as key/value[array] structure
     */
    protected Collection $allowedTagToAttributeMap;

    /**
     * Instance of HtmlSanitizerConfig config
     */
    protected ?HtmlSanitizerConfig $htmlSanitizerConfig = null;

    /**
     * Create a new instance
     */
    public function __construct(string $allowable)
    {
        $this->allowedTagToAttributeMap = $this->generateAllowedTagToAttributeMap(Config::getVar('security', $allowable, null) ?? $allowable);
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
        if (!$this->htmlSanitizerConfig) {
            $this->buildSanitizerConfig();
        }

        return $this->htmlSanitizerConfig;
    }

    /**
     * Sanitize the given html string
     */
    public function sanitize(string $html): string
    {   
        $config = clone $this->getSanitizerConfig();

        $this
            ->getAllNotAllowedHtmlTags($html)
            ->each(function(string $tag) use (&$config) {
                $config = $config->blockElement($tag);
            });
        
        return (new HtmlSanitizer($config))->sanitize($html);
    }

    /**
     * Build up the HtmlSanitizerConfig instance if no predefined instance provided
     */
    protected function buildSanitizerConfig(): void
    {
        $this->htmlSanitizerConfig = (new HtmlSanitizerConfig())
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowMediaSchemes(['https', 'http']);
        
        $this->allowedTagToAttributeMap->each(
            fn (array $attributes, string $tag) => $this->htmlSanitizerConfig = $this->htmlSanitizerConfig->allowElement($tag, $attributes)
        );
    }

    /**
     * Get the collection of non allowed tags in the given html srting
     */
    protected function getAllNotAllowedHtmlTags(string $html): Collection
    {
        if (empty($html)) {
            return collect([]);
        }
        
        $dom = new DOMDocument();

        $errorState = libxml_use_internal_errors(true); // Don't generate warnings on malformed HTML
        $dom->loadHTML($html);
        libxml_use_internal_errors($errorState); // Restore the error state to its previous value

        return collect($dom->getElementsByTagName('*'))
            ->map(fn ($child) => $child->nodeName)
            ->diff($this->allowedTagToAttributeMap->keys());
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