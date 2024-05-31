<?php

/**
 * @file classes/invitation/invitations/PKPReceiveInvitationController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPReceiveInvitationController
 *
 * @brief Interface for all Invitation API Handlers
 */

namespace PKP\invitation\invitations;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;

abstract class PKPReceiveInvitationController extends Controller
{
    abstract public function authorize(PKPBaseController $controller, PKPRequest $request, array &$args, array $roleAssignments): bool;
    abstract public function receive(Request $illuminateRequest): JsonResponse;
    abstract public function refine(Request $illuminateRequest): JsonResponse;
    abstract public function finalize(Request $illuminateRequest): JsonResponse;
    abstract public function decline(Request $illuminateRequest): JsonResponse;
}