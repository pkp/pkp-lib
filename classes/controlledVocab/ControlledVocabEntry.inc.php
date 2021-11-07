<?php

/**
 * @file classes/controlledVocab/ControlledVocabEntry.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ControlledVocabEntry
 * @ingroup controlled_vocabs
 *
 * @see ControlledVocabEntryDAO
 *
 * @brief Basic class describing a controlled vocab.
 */

namespace PKP\controlledVocab;

class ControlledVocabEntry extends \PKP\core\DataObject
{
    //
    // Get/set methods
    //

    /**
     * Get the ID of the controlled vocab.
     *
     * @return int
     */
    public function getControlledVocabId()
    {
        return $this->getData('controlledVocabId');
    }

    /**
     * Set the ID of the controlled vocab.
     *
     * @param int $controlledVocabId
     */
    public function setControlledVocabId($controlledVocabId)
    {
        $this->setData('controlledVocabId', $controlledVocabId);
    }

    /**
     * Get sequence number.
     *
     * @return float
     */
    public function getSequence()
    {
        return $this->getData('sequence');
    }

    /**
     * Set sequence number.
     *
     * @param float $sequence
     */
    public function setSequence($sequence)
    {
        $this->setData('sequence', $sequence);
    }

    /**
     * Get the localized name.
     *
     * @return string
     */
    public function getLocalizedName()
    {
        return $this->getLocalizedData('name');
    }

    /**
     * Get the name of the controlled vocabulary entry.
     *
     * @param string $locale
     *
     * @return string
     */
    public function getName($locale)
    {
        return $this->getData('name', $locale);
    }

    /**
     * Set the name of the controlled vocabulary entry.
     *
     * @param string $name
     * @param string $locale
     */
    public function setName($name, $locale)
    {
        $this->setData('name', $name, $locale);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controlledVocab\ControlledVocabEntry', '\ControlledVocabEntry');
}
