<?php
/**
 * @file plugins/importexport/native/filter/PKPNativeFilterHelper.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNativeFilterHelper
 * @ingroup plugins_importexport_native
 *
 * @brief Class that provides native import/export filter-related helper methods.
 */

namespace PKP\plugins\importexport\native\filter;

use APP\file\PublicFileManager;
use APP\publication\Publication;
use DOMDocument;
use DOMElement;
use PKP\core\PKPApplication;

class PKPNativeFilterHelper
{
    /**
     * Create and return an object covers node.
     */
    public function createPublicationCoversNode(NativeExportFilter $filter, DOMDocument $doc, Publication $object): ?DOMElement
    {
        $coverImages = $object->getData('coverImage');
        if (empty($coverImages)) {
            return null;
        }

        $deployment = $filter->getDeployment();
        $context = $deployment->getContext();
        $publicFileManager = new PublicFileManager();
        $contextId = $context->getId();
        $coversNode = $doc->createElementNS($deployment->getNamespace(), 'covers');
        foreach ($coverImages as $locale => $coverImage) {
            $coverImageName = $coverImage['uploadName'] ?? '';

            $filePath = $publicFileManager->getContextFilesPath($contextId) . '/' . $coverImageName;
            if (!file_exists($filePath)) {
                $deployment->addWarning(PKPApplication::ASSOC_TYPE_PUBLICATION, $object->getId(), __('plugins.importexport.common.error.publicationCoverImageMissing', ['id' => $object->getId(), 'path' => $filePath]));
                continue;
            }

            $coverNode = $doc->createElementNS($deployment->getNamespace(), 'cover');
            $coverNode->setAttribute('locale', $locale);
            $coverNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'cover_image', htmlspecialchars($coverImageName, ENT_COMPAT, 'UTF-8')));
            $coverNode->appendChild($doc->createElementNS($deployment->getNamespace(), 'cover_image_alt_text', htmlspecialchars($coverImage['altText'] ?? '', ENT_COMPAT, 'UTF-8')));

            $embedNode = $doc->createElementNS($deployment->getNamespace(), 'embed', base64_encode(file_get_contents($filePath)));
            $embedNode->setAttribute('encoding', 'base64');
            $coverNode->appendChild($embedNode);
            $coversNode->appendChild($coverNode);
        }

        return $coversNode->firstChild?->parentNode;
    }

    /**
     * Parse out the object covers.
     *
     * @param NativeExportFilter $filter
     * @param \DOMElement $node
     * @param Publication $object
     */
    public function parsePublicationCovers($filter, $node, $object)
    {
        $deployment = $filter->getDeployment();

        $coverImages = [];

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof \DOMElement) {
                switch ($n->tagName) {
                    case 'cover':
                        $coverImage = $this->parsePublicationCover($filter, $n, $object);
                        $coverImages[key($coverImage)] = reset($coverImage);
                        break;
                    default:
                        $deployment->addWarning(PKPApplication::ASSOC_TYPE_PUBLICATION, $object->getId(), __('plugins.importexport.common.error.unknownElement', ['param' => $n->tagName]));
                }
            }
        }

        $object->setData('coverImage', $coverImages);
    }

    /**
     * Parse out the cover and store it in the object.
     *
     * @param NativeExportFilter $filter
     * @param \DOMElement $node
     * @param Publication $object
     */
    public function parsePublicationCover($filter, $node, $object)
    {
        $deployment = $filter->getDeployment();

        $context = $deployment->getContext();

        $locale = $node->getAttribute('locale');
        if (empty($locale)) {
            $locale = $context->getPrimaryLocale();
        }

        $coverImagelocale = [];
        $coverImage = [];

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if ($n instanceof \DOMElement) {
                switch ($n->tagName) {
                    case 'cover_image':
                        $coverImage['uploadName'] = $n->textContent;
                        break;
                    case 'cover_image_alt_text':
                        $coverImage['altText'] = $n->textContent;
                        break;
                    case 'embed':
                        $publicFileManager = new PublicFileManager();
                        $filePath = $publicFileManager->getContextFilesPath($context->getId()) . '/' . $coverImage['uploadName'];
                        file_put_contents($filePath, base64_decode($n->textContent));
                        break;
                    default:
                        $deployment->addWarning(PKPApplication::ASSOC_TYPE_PUBLICATION, $object->getId(), __('plugins.importexport.common.error.unknownElement', ['param' => $n->tagName]));
                }
            }
        }

        $coverImagelocale[$locale] = $coverImage;

        return $coverImagelocale;
    }
}
