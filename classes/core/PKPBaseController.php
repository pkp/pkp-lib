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

    abstract public function getHandlerPath(): string;

    abstract public function getRouteGroupMiddlewares(): array;

    abstract public function getGroupRoutesCallback(): Closure;
}