<?php

/**
 * @file classes/emailTemplate/EmailTemplate.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailTemplate
 *
 * @ingroup mail
 *
 * @see EmailTemplateDAO
 *
 * @brief Describes basic email template properties.
 */

namespace PKP\emailTemplate;

class EmailTemplate extends \PKP\core\DataObject
{
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\emailTemplate\EmailTemplate', '\EmailTemplate');
}
