<?php

namespace PKP\core;

use Closure;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class PKPBaseController extends Controller
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    public function getPathPattern(): ?string
    {
        return null;
    }

    public function isSiteWide(): bool
    {
        return false;
    }

    abstract public function getHandlerPath(): string;

    abstract public function getRouteGroupMiddleware(): array;

    abstract public function getGroupRoutes(): void;
}