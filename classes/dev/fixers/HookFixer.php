<?php

declare(strict_types=1);

namespace PKP\dev\fixers;

use PhpCsFixer\DocBlock\DocBlock;
use PhpCsFixer\DocBlock\Line;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Preg;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

class HookFixer implements FixerInterface
{
    public function getName(): string
    {
        return 'PKP/hookfixer';
    }

    public function supports(\SplFileInfo $file): bool
    {
        return true;
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Hooks in code must be included as PHPDoc @hook documentation.',
            [new CodeSample('<?php
/**
 * @hook Hook::Name::Here
 */
function foo() {
    Hook::call(\'Hook::Name::Here\', ...);
}
')],
            '',
        );
    }

    public function getPriority(): int
    {
        return 4;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAllTokenKindsFound([\T_DOC_COMMENT, \T_FUNCTION]);
    }

    public function isRisky(): bool
    {
        return false;
    }

    public function fix(\SplFileInfo $file, Tokens $tokens): void
    {
        for ($index = 0; $index < $tokens->count(); $index++) {

            if (!$tokens[$index]->isGivenKind(\T_DOC_COMMENT)) {
                continue;
            }

            // ignore one-line phpdocs like `/** foo */`, as there is no place to put new annotations
            if (!str_contains($tokens[$index]->getContent(), "\n")) {
                continue;
            }

            $functionIndex = $tokens->getTokenNotOfKindSibling($index, 1, [[\T_ABSTRACT], [\T_COMMENT], [\T_FINAL], [\T_PRIVATE], [\T_PROTECTED], [\T_PUBLIC], [\T_STATIC], [\T_WHITESPACE]]);
            if ($functionIndex === null) {
                return;
            }
            if (!$tokens[$functionIndex]->isGivenKind(\T_FUNCTION)) {
                continue;
            }

            // $index now points at a docblock for a function, and $functionIndex points at the function.

            $openFunctionBraceIndex = $tokens->getNextTokenOfKind($functionIndex, ['{']);
            if (!$openFunctionBraceIndex) {
                continue;
            }
            $closeFunctionBraceIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $openFunctionBraceIndex);
            \assert(\is_int($closeFunctionBraceIndex));

            // $openFunctionBraceIndex .. $closeFunctionBraceIndex now identify the function's contents

            $i = $openFunctionBraceIndex;
            while (true) {
                // Find a Hook::[string] sequence
                $sequence = $tokens->findSequence([[\T_STRING, 'Hook'], [\T_DOUBLE_COLON], [\T_STRING]], $i, $closeFunctionBraceIndex);
                if (!$sequence) {
                    break;
                }

                [0 => $hookIndex, 2 => $functionNameIndex] = array_keys($sequence);
                $i = $functionNameIndex + 1;

                // Look for Hook::run or Hook::call; otherwise skip ahead
                $hookCallOrRun = $tokens[$functionNameIndex]->getContent();
                if (!in_array($hookCallOrRun, ['call', 'run'])) {
                    continue;
                }

                // Find the hook name
                $openBracketIndex = $tokens->getNextTokenOfKind($functionNameIndex, ['(']);
                if (!$openBracketIndex) {
                    continue;
                }
                $stringIndex = $tokens->getNextMeaningfulToken($openBracketIndex);
                if (!$tokens[$stringIndex]->isGivenKind(\T_CONSTANT_ENCAPSED_STRING)) {
                    continue;
                }

                // A hook call has been identified.
                $hookName = substr($tokens[$stringIndex]->getContent(), 1, -1);

                // Try to identify the hook parameter list, if possible.
                $commaIndex = $tokens->getNextMeaningfulToken($stringIndex);
                $parameterString = '';
                if ($commaIndex && $tokens[$commaIndex]->equals(',')) {
                    $parameterListStartIndex = $tokens->getNextMeaningfulToken($commaIndex);
                    if ($tokens[$parameterListStartIndex]->isGivenKind(CT::T_ARRAY_SQUARE_BRACE_OPEN)) {
                        $parameterListEndIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_ARRAY_SQUARE_BRACE, $parameterListStartIndex);
                        \assert(\is_int($parameterListEndIndex));

                        // Found a parameter list between $parameterListStartIndex and $parameterListEndIndex.
                        $lastTokenWasWhitespace = false;
                        for ($j = $parameterListStartIndex + 1; $j < $parameterListEndIndex; $j++) {
                            if ($tokens[$j]->equals([T_WHITESPACE])) {
                                if (!$lastTokenWasWhitespace) {
                                    $parameterString .= ' ';
                                    $lastTokenWasWhitespace = true;
                                }
                            } else {
                                $lastTokenWasWhitespace = false;
                                $parameterString .= $tokens[$j]->getContent();
                            }
                        }
                    }
                }

                // Try to identify a matching @hook annotation in the docblock.
                $doc = new DocBlock($tokens[$index]->getContent());
                $found = false;
                $lastHookLine = null;
                foreach ($doc->getAnnotationsOfType('hook') as $annotation) {
                    if (Preg::match('/@hook\s+\Q' . $hookName . '\E\b/', $annotation->getContent(), $matches)) {
                        $found = true;
                    }
                    $lastHookLine = max($lastHookLine, $annotation->getEnd());
                }

                // If the @hook annotation wasn't found, add one.
                if (!$found) {
                    $lines = $doc->getLines();
                    $linesCount = \count($lines);
                    Preg::match('/^(\s*).*$/', $lines[$linesCount - 1]->getContent(), $matches);
                    $indent = $matches[1];

                    $newLine = new Line(sprintf(
                        '%s* @hook %s %s%s',
                        $indent,
                        $hookName,
                        $parameterString !== '' ? (($hookCallOrRun == 'call' ? '[' : '') . "[{$parameterString}]" . ($hookCallOrRun == 'call' ? ']' : '')) : '',
                        "\n"
                    ));
                    array_splice(
                        $lines,
                        $lastHookLine ? $lastHookLine + 1 : $linesCount - 1,
                        0,
                        [$newLine]
                    );
                    error_log(print_r($lines, true));
                    $tokens[$index] = new Token([T_DOC_COMMENT, implode('', $lines)]);
                }
            };
        }
    }
}
