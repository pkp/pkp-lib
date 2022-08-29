<?php

/**
 * @defgroup decision Decision
 */

/**
 * @file classes/decision/Decision.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Decision
 * @ingroup decision
 *
 * @see DAO
 *
 * @brief An editorial decision taken on a submission, such as to accept, decline or request revisions.
 */

namespace APP\decision;

use PKP\decision\Decision as BaseDecision;

/**
 * The decision constants are not used in OPS, but they are required
 * in order to prevent errors in the pkp-lib code. They should be
 * removed once the constants have been synced across OJS/OMP and moved
 * into pkp-lib.
 *
 * @see https://github.com/pkp/pkp-lib/issues/7725
 */
class Decision extends BaseDecision
{
    /** @deprecated */
    public const ACCEPT = 1;

    /** @deprecated */
    public const PENDING_REVISIONS = 2;

    /** @deprecated */
    public const RESUBMIT = 3;

    /** @deprecated */
    public const DECLINE = 4;

    /** @deprecated */
    public const SEND_TO_PRODUCTION = 7;

    /** @deprecated */
    public const EXTERNAL_REVIEW = 8;

    /** @deprecated */
    public const RECOMMEND_ACCEPT = 11;

    /** @deprecated */
    public const RECOMMEND_PENDING_REVISIONS = 12;

    /** @deprecated */
    public const RECOMMEND_RESUBMIT = 13;

    /** @deprecated */
    public const RECOMMEND_DECLINE = 14;

    /** @deprecated */
    public const NEW_EXTERNAL_ROUND = 16;

    /** @deprecated */
    public const REVERT_DECLINE = 17;

    /** @deprecated */
    public const SKIP_EXTERNAL_REVIEW = 19;

    /** @deprecated */
    public const BACK_TO_REVIEW = 20;

    /** @deprecated */
    public const BACK_TO_COPYEDITING = 21;

    /** @deprecated */
    public const BACK_TO_SUBMISSION_FROM_COPYEDITING = 22;
}
