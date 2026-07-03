<?php

/**
 * @file classes/mail/variables/PublicationEmailVariable.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationEmailVariable
 *
 * @ingroup mail_variables
 *
 * @brief Represents variables associated with a publication that can be assigned to a template
 */

namespace PKP\mail\variables;

use APP\publication\Publication;
use PKP\context\Context;
use PKP\core\PKPString;
use PKP\mail\Mailable;

abstract class PublicationEmailVariable extends Variable
{
    public const PUBLICATION_ABSTRACT = 'publicationAbstract';
    public const PUBLICATION_ID = 'publicationId';
    public const PUBLICATION_PUBLISHED_URL = 'publicationPublishedUrl';
    public const PUBLICATION_TITLE = 'publicationTitle';

    public function __construct(protected Publication $publication, Mailable $mailable)
    {
        parent::__construct($mailable);
    }

    /**
     * @copydoc Variable::descriptions()
     */
    public static function descriptions(): array
    {
        return
        [
            self::PUBLICATION_ABSTRACT => __('emailTemplate.variable.publication.publicationAbstract'),
            self::PUBLICATION_ID => __('emailTemplate.variable.publication.publicationId'),
            self::PUBLICATION_PUBLISHED_URL => __('emailTemplate.variable.publication.publicationPublishedUrl'),
            self::PUBLICATION_TITLE => __('emailTemplate.variable.publication.publicationTitle'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    public function values(string $locale): array
    {
        $context = $this->getContext();
        return
        [
            self::PUBLICATION_ABSTRACT => PKPString::stripUnsafeHtml($this->publication->getLocalizedData('abstract', $locale)),
            self::PUBLICATION_ID => (string) $this->publication->getId(),
            self::PUBLICATION_PUBLISHED_URL => $this->getPublishedUrl($this->getContext()),
            self::PUBLICATION_TITLE => $this->publication->getLocalizedFullTitle($locale, 'html'),
        ];
    }

    /**
     * URL to the published publication
     */
    abstract protected function getPublishedUrl(Context $context): string;
}
