<?php

/**
 * @file classes/log/event/SubmissionFieldChangeFormatter.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFieldChangeFormatter
 *
 * @brief Renders the log data for generic submission field changes.
 */

namespace PKP\controllers\grid\eventLog;

use PKP\core\PKPString;
use PKP\facades\Locale;

class SubmissionFieldChangeFormatter
{
    /**
     * Takes a generic log data array and renders it as HTML.
     *
     * @param array{
     *      fieldName: array{
     *          label: string,
     *          labelIsKey?: bool
     *      },
     *      data: array<array{
     *          label: string,
     *          labelIsKey?: bool,
     *          type: 'text'|'list',
     *          new: string|int|bool,
     *          old: string|int|bool
     *      }>
     *  }
     * $value The log entry data as an assoc array.
     *
     *  `fieldName`:
     *      The name of the field that was changed.
     *      - `labelIsKey` defaults to true. Indicates whether the label is a locale key or a string to be displayed as-is.
     *
     *
     *  `data`:
     *   - Individual record of data that was changed. Example: `authorResponse` & `associatedAuthors`
     *      - `label` is displayed above the field data.
     *      - `labelIsKey` defaults to true. Indicates whether the label is a locale key or a string to be displayed as-is.
     *      - `type` determines how the data is presented in the UI. 'text' can be used as a blanket type for all non-list data.
     *      - `new` is the new value of the field. This should be a scalar value or multilingual text ready for display without additional processing
     *      - `old` is the old value of the field. This should be a scalar value or multilingual text ready for display without additional processing
     */
    public function format(array $value): string
    {
        $locale = Locale::getLocale();
        $html = '';

        foreach (['new' => 'submission.event.field.updated', 'old' => 'submission.event.field.previous'] as $section => $sectionLabel) {
            $body = '';
            foreach ($value['data'] ?? [] as $field) {
                if (!array_key_exists($section, $field)) {
                    continue;
                }

                $body .= '<div class="mt-2">'
                    . (isset($field['label']) ? '<em>' . $this->label($field) . '</em>' : '')
                    . $this->value($field['type'] ?? null, $field[$section], $locale) . '</div>';
            }

            if ($body !== '') {
                $fieldName = $value['fieldName'];
                $isFieldNameKey = !array_key_exists('labelIsKey', $fieldName) || (bool)($fieldName['labelIsKey']);
                $filedNameLabel = $isFieldNameKey ? __($fieldName['label']) : $fieldName['label'];

                $html .= '<div class="mt-4"><strong>' .
                    htmlspecialchars($isFieldNameKey ? __($sectionLabel, ['fieldName' => $filedNameLabel]) : $filedNameLabel) .
                    '</strong><div>' . $body . '</div></div>';
            }
        }

        return '<form class="pkp_form"><div class="mt-6">' . $html . '</div></form>';
    }

    /**
     * Get the label for a field.
     */
    protected function label(array $field): string
    {
        $label = (string)($field['label'] ?? '');

        return htmlspecialchars(($field['labelIsKey'] ?? true) ? __($label) : $label);
    }

    /**
     * Format the data stored in a field.
     *
     * @param string|null $type The type of data. Can be 'text' or 'list'.
     * @param mixed $value The value of the field. If the value is an array and the array is not a sequential list, then the value is assumed to be multilingual text.
     * @param string $locale The locale to use for multilingual data.
     */
    protected function value(?string $type, mixed $value, string $locale): string
    {
        /**
         * For localized data, we get the value for the current locale. Else, default to the first value in the data.
         */
        if (is_array($value) && $value !== [] && !array_is_list($value)) {
            $value = $value[$locale] ?? reset($value);
        }

        if ($value === null || $value === '' || $value === []) {
            return '-';
        }

        $type ??= is_array($value) && array_is_list($value) ? 'list' : 'text';

        return match ($type) {
            'list' => '<ul>' . implode('', array_map(fn ($item) => '<li>' . htmlspecialchars((string)$item) . '</li>', (array)$value)) . '</ul>',
            // Default will handle any value that doesn't have an explicit type.
            // It will also handle the `text` type. The `text` type can be used for any none-list value, including multilingual text
            default => '<div>' . PKPString::stripUnsafeHtml((string)$value) . '</div>',
        };
    }
}
