<?php

/**
 * @file classes/plugins/PKPPubIdPluginDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPubIdPluginDAO
 *
 * @ingroup plugins
 *
 * @brief Interface that DAOs would need to implement in order for pub ID support to be added.
 */

namespace PKP\plugins;

interface PKPPubIdPluginDAO
{
    /**
     * Checks if public identifier exists (other than for the specified
     * submission ID, which is treated as an exception).
     *
     * @param $pubIdType One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
     * @param $excludePubObjectId ID of the pub object to be excluded from the search.
     */
    public function pubIdExists(string $pubIdType, string $pubId, int $excludePubObjectId, int $contextId): bool;

    /**
     * Change the public ID of a submission.
     *
     * @param int $pubObjectId ID of the pub object
     * @param string $pubIdType One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
     * @param string $pubId
     */
    public function changePubId($pubObjectId, $pubIdType, $pubId);

    /**
     * Delete the public ID of a submission.
     *
     * @param $pubObjectId ID of the pub object
     * @param $pubIdType One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
     */
    public function deletePubId(int $pubObjectId, string $pubIdType): int;

    /**
     * Delete the public IDs of all submissions in this context.
     *
     * @param $pubIdType One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
     */
    public function deleteAllPubIds(int $contextId, string $pubIdType): int;
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\PKPPubIdPluginDAO', '\PKPPubIdPluginDAO');
}
