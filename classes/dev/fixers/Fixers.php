<?php

namespace PKP\dev\fixers;

use Generator;
use IteratorAggregate;

final class Fixers implements IteratorAggregate
{
    public function getIterator(): Generator
    {
        foreach ([new HookFixer()] as $fixer) {
            yield $fixer;
        }
    }
}
