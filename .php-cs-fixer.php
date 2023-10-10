<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    // The next two rules are enabled by default, kept for clarity
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    // The pattern is matched against each found filename, thus:
    // - The "/" is needed to avoid having "vendor" match "Newsvendor.php"
    // - The presence of "node_modules" here doesn't prevent the Finder from recursing on it, so we merge these paths below at the "exclude()"
    ->notPath($ignoredDirectories = ['cypress/', 'js/', 'locale/', 'node_modules/', 'styles/', 'templates/', 'vendor/'])
    // Ignore root based directories
    ->exclude(array_merge($ignoredDirectories, ['dtd', 'lib', 'registry', 'schemas', 'xml']));

$rules = include '.php_cs_rules';

$config = new PhpCsFixer\Config();
return $config->setRules($rules)
    ->setFinder($finder);
