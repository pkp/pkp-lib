<?php

/**
 * @file classes/user/InterestEntry.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Interest
 * @ingroup user
 *
 * @see InterestDAO
 *
 * @brief Basic class describing a reviewer interest
 */

namespace PKP\user;

class InterestEntry extends \PKP\controlledVocab\ControlledVocabEntry
{
    //
    // Get/set methods
    //

    /**
     * Get the interest
     *
     * @return string
     */
    public function getInterest()
    {
        return $this->getData('interest');
    }

    /**
     * Set the interest text
     *
     * @param string $interest
     */
    public function setInterest($interest)
    {
        $this->setData('interest', $interest);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\InterestEntry', '\InterestEntry');
}
