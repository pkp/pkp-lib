<?php

/**
 * @file classes/log/contracts/iSubmissionIntroducer.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorAction
 * @ingroup submission_action
 *
 * @brief Editor actions.
 */

namespace PKP\log\contracts;

interface iSubmissionIntroducer
{
    public function getSubmissionIntroducerEventEntry(): SubmissionIntroducerEventEntry;
}
