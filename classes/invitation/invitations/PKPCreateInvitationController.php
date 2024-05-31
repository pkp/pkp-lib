<?php

/**
 * @file classes/invitation/invitations/PKPCreateInvitationController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCreateInvitationController
 *
 * @brief Interface for all Invitation API Handlers
 */

namespace PKP\invitation\invitations;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;

abstract class PKPCreateInvitationController extends Controller
{
    abstract public function authorize(PKPBaseController $controller, PKPRequest $request, array &$args, array $roleAssignments): bool;
    abstract public function add(Request $illuminateRequest): JsonResponse;
    abstract public function populate(Request $illuminateRequest): JsonResponse;
    abstract public function dispatch(Request $illuminateRequest): JsonResponse;
    abstract public function get(Request $illuminateRequest): JsonResponse;
}