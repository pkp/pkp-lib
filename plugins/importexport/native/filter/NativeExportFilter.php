<?php

/**
 * @file plugins/importexport/native/filter/NativeExportFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NativeExportFilter
 *
 * @ingroup plugins_importexport_native
 *
 * @brief Base class that converts a DataObject to a Native XML document
 */

namespace PKP\plugins\importexport\native\filter;

use PKP\plugins\importexport\PKPImportExportFilter;
use PKP\xslt\XMLTypeDescription;

abstract class NativeExportFilter extends PKPImportExportFilter
{
    /** @var bool If set to true no validation (e.g. XML validation) will be done */
    public ?bool $_noValidation = null;
    public array $opts = [];

    /**
     * Set no validation option
     */
    public function setNoValidation(bool $noValidation): void
    {
        $this->_noValidation = $noValidation;
    }

    /**
     * Get no validation option
     */
    public function getNoValidation(): ?bool
    {
        return $this->_noValidation;
    }

    //
    // Public methods
    //
    /**
     * @copydoc Filter::supports()
     */
    public function supports(&$input, &$output): bool
    {
        // Validate input
        $inputType = & $this->getInputType();
        $validInput = $inputType->isCompatible($input);

        // If output is null then we're done
        if (is_null($output)) {
            return $validInput;
        }

        // Validate output
        $outputType = & $this->getOutputType();

        if ($outputType instanceof XMLTypeDescription && $this->getNoValidation()) {
            $outputType->setValidationStrategy(XMLTypeDescription::XML_TYPE_DESCRIPTION_VALIDATE_NONE);
        }
        $validOutput = $outputType->isCompatible($output);

        return $validInput && $validOutput;
    }

    //
    // Helper functions
    //
    /**
     * Create a set of child nodes of parentNode containing the
     * localeKey => value data representing translated content.
     *
     * @param string $name Node name
     * @param $values Array of locale key => value mappings, or null for no values
     */
    public function createLocalizedNodes(\DOMDocument $doc, \DOMNode $parentNode, string $name, ?array $values): void
    {
        $deployment = $this->getDeployment();
        foreach (is_array($values) ? $values : [] as $locale => $value) {
            if ($value === '') { // Skip empty values
                continue;
            }

            $node = $doc->createElementNS($deployment->getNamespace(), $name, htmlspecialchars($value, ENT_COMPAT));
            $node->setAttribute('locale', $locale);
            $parentNode->appendChild($node);
        }
    }

    /**
     * Create an optional node with a name and value.
     */
    public function createOptionalNode(\DOMDocument $doc, \DOMElement $parentNode, string $name, ?string $value): ?\DOMElement
    {
        if ($value === '' || $value === null) {
            return null;
        }

        $deployment = $this->getDeployment();
        $parentNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), $name, htmlspecialchars($value, ENT_COMPAT, 'UTF-8')));
        return $node;
    }

    /**
     * Set xml filtering opts
     */
    public function setOpts(array $opts): void
    {
        $this->opts = $opts;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\importexport\native\filter\NativeExportFilter', '\NativeExportFilter');
}
