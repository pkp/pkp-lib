<?php

/**
 * @file classes/invitation/core/CreateInvitationController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CreateInvitationController
 *
 * @brief Defines the API actions of the "Create" phase of the invitation
 */

namespace PKP\invitation\core;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;

abstract class CreateInvitationController extends Controller
{
    public ?PKPRequest $request = null;

    abstract public function authorize(PKPBaseController $controller, PKPRequest $request, array &$args, array $roleAssignments): bool;
    abstract public function add(Request $illuminateRequest): JsonResponse;
    abstract public function populate(Request $illuminateRequest): JsonResponse;
    abstract public function invite(Request $illuminateRequest): JsonResponse;
    abstract public function get(Request $illuminateRequest): JsonResponse;

    abstract public function cancel(): JsonResponse;
    abstract public function getMailable(): JsonResponse;
    abstract public function getMany(Request $illuminateRequest, Builder $query): Collection;
}
