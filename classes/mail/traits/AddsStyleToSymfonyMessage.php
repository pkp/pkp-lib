<?php

/**
 * @file classes/mail/traits/AddsStyleToSymfonyMessage.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddsStyleToSymfonyMessage
 *
 * @brief Mailable trait to add style to mailable
 */

namespace PKP\mail\traits;

use PKP\core\Core;
use Symfony\Component\Mime\Email;

trait AddsStyleToSymfonyMessage
{
    protected function mailCssPath(): ?string
    {
        return Core::getBaseDir() . '/lib/pkp/styles/mailables/style.css';
    }

    protected function registerMailCss(): void
    {
        $path = $this->mailCssPath();
        if (!$path || !is_file($path)) {
            return;
        }

        static $cssCache = [];
        $css = $cssCache[$path] ??= (string) @file_get_contents($path);

        if ($css === '') {
            return;
        }

        $this->withSymfonyMessage(function (Email $message) use ($css) {
            $html = $message->getHtmlBody();
            if (!$html) {
                return;
            }

            $styleTag = "<style>{$css}</style>";

            $html = $styleTag . $html;

            $message->html($html);
        });
    }
}