<?php

/**
 * @file plugins/oaiMetadataFormats/dc/PKPOAIMetadataFormat_DC.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPOAIMetadataFormat_DC
 *
 * @see OAI
 *
 * @brief OAI metadata format class -- Dublin Core.
 */

namespace PKP\plugins\oaiMetadataFormats\dc;

use PKP\core\DataObject;
use PKP\core\PKPString;
use PKP\metadata\MetadataDescription;
use PKP\metadata\MetadataProperty;
use PKP\oai\OAIUtils;

class PKPOAIMetadataFormat_DC extends \PKP\oai\OAIMetadataFormat
{
    /**
     * @copydoc OAIMetadataFormat::toXML
     *
     * @param DataObject $dataObject
     * @param null|mixed $format
     */
    public function toXml($dataObject, $format = null)
    {
        $dcDescription = $dataObject->extractMetadata(new \APP\plugins\metadata\dc11\schema\Dc11Schema());

        $response = "<oai_dc:dc\n" .
            "\txmlns:oai_dc=\"http://www.openarchives.org/OAI/2.0/oai_dc/\"\n" .
            "\txmlns:dc=\"http://purl.org/dc/elements/1.1/\"\n" .
            "\txmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n" .
            "\txsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/oai_dc/\n" .
            "\thttp://www.openarchives.org/OAI/2.0/oai_dc.xsd\">\n";

        foreach ($dcDescription->getProperties() as $propertyName => $property) { /** @var MetadataProperty $property */
            if ($dcDescription->hasStatement($propertyName)) {
                if ($property->getTranslated()) {
                    $values = $dcDescription->getStatementTranslations($propertyName);
                } else {
                    $values = $dcDescription->getStatement($propertyName);
                }
                $response .= $this->formatElement($propertyName, $values, $property->getTranslated());
            }
        }

        $response .= "</oai_dc:dc>\n";

        return $response;
    }

    /**
     * Format XML for single DC element.
     *
     * @param string $propertyName
     * @param bool $multilingual optional
     */
    public function formatElement($propertyName, $values, $multilingual = false)
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        // Translate the property name to XML syntax.
        $openingElement = str_replace(['[@', ']'], [' ',''], $propertyName);
        $closingElement = PKPString::regexp_replace('/\[@.*/', '', $propertyName);

        // Create the actual XML entry.
        $response = '';
        foreach ($values as $key => $value) {
            if ($multilingual) {
                $key = str_replace('_', '-', $key);
                assert(is_array($value));
                foreach ($value as $subValue) {
                    if ($key == MetadataDescription::METADATA_DESCRIPTION_UNKNOWN_LOCALE) {
                        $response .= "\t<{$openingElement}>" . OAIUtils::prepOutput($subValue) . "</{$closingElement}>\n";
                    } else {
                        $response .= "\t<{$openingElement} xml:lang=\"{$key}\">" . OAIUtils::prepOutput($subValue) . "</{$closingElement}>\n";
                    }
                }
            } else {
                assert(is_scalar($value));
                $response .= "\t<{$openingElement}>" . OAIUtils::prepOutput($value) . "</{$closingElement}>\n";
            }
        }
        return $response;
    }
}
