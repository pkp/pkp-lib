<?php
/**
 * @file controllers/grid/form/LogResponseForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReinstateReviewerForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Allow the editor to reinstate a cancelled review assignment
 */

namespace PKP\controllers\grid\users\reviewer\form;

use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\submission\reviewAssignment\ReviewAssignment;
use APP\template\TemplateManager;

import('lib.pkp.controllers.grid.users.reviewer.form.ReviewerNotifyActionForm');

class LogResponseForm extends Form {

    /** @var ReviewAssignment The review assignment associated with the reviewer */
    public $_reviewAssignment;

    /** @var array Arguments used to route the form op */
    var $_requestArgs;

    /**
     * Constructor.
     * @param $requestArgs array Arguments used to route th e form op to the
     *  correct submission, stage and review round
     * @param $reviewAssignment
     */
    function __construct($requestArgs, $reviewAssignment) {
        parent::__construct('controllers/grid/users/reviewer/form/logResponseForm.tpl');
        $this->_requestArgs = $requestArgs;
        $this->_reviewAssignment = $reviewAssignment;
        $this->addCheck(new FormValidator($this, 'logResponse', 'required', 'common.requiredField'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::readInputData()
     */
    function readInputData() {
        $this->readUserVars(array(
            'logResponse',
        ));
    }

    /**
     * Display the form.
     * @param $request
     * @param $template
     * @param $display
     * @param $requestArgs array Request parameters to bounce back with the form submission.
     * @see Form::fetch
     */
    function fetch($request, $template = null, $display = false, $requestArgs = array()) {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('requestArgs', $requestArgs);

        return parent::fetch($request, $template, $display);
    }
}
