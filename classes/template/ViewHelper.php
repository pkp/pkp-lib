<?php

/**
 * @file lib/pkp/classes/template/ViewHelper.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ViewHelper
 * @brief Helper class providing utility methods for view templates
 */

namespace PKP\template;

use PKP\core\PKPString;

class ViewHelper
{
    /**
     * Generate a URL using PKPTemplateManager
     *
     * @param array $parameters URL parameters (page, op, path, etc.)
     * @return string The generated URL
     */
    public static function url(array $parameters): string
    {
        return PKPTemplateManager::getManager()->smartyUrl($parameters);
    }

    /**
     * Format a date with locale-aware formatting
     * Delegates to PKPTemplateManager::smartyDateFormat() for consistency
     *
     * @param string|null $dateString The date string to format
     * @param string|null $format The date format (if null, uses default)
     * @return string The formatted date string
     */
    public static function dateFormat($dateString, ?string $format = null): string
    {
        return PKPTemplateManager::getManager()->smartyDateFormat($dateString, $format);
    }

    /**
     * Sanitize HTML content
     *
     * @param string|null $input The HTML content to sanitize
     * @param string $configKey The configuration key for allowed HTML tags
     * @return string The sanitized HTML
     */
    public static function sanitizeHtml(string $input, string $configKey = 'allowed_html'): string
    {
    
        $result = PKPString::stripUnsafeHtml($input, $configKey);
        $result = str_replace('{{', '<span v-pre>{{</span>', $result);
        $result = str_replace('}}', '<span v-pre>}}</span>', $result);
        return $result;
    }
}
