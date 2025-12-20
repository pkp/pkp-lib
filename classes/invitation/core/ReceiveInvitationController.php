<?php

/**
 * @file classes/invitation/core/ReceiveInvitationController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReceiveInvitationController
 *
 * @brief Defines the API actions of the "Receive" phase of the invitation
 */

namespace PKP\invitation\core;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;

abstract class ReceiveInvitationController extends Controller
{
    abstract public function authorize(PKPBaseController $controller, PKPRequest $request, array &$args, array $roleAssignments): bool;
    abstract public function receive(Request $illuminateRequest): JsonResponse;
    abstract public function refine(Request $illuminateRequest): JsonResponse;
    abstract public function finalize(Request $illuminateRequest): JsonResponse;
    abstract public function decline(Request $illuminateRequest): JsonResponse;
}
