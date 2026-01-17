<?php
use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/scripts')
    ->name('*.php')
    ->exclude('vendor');

return (new Config())
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'blank_line_before_statement' => ['statements' => ['return']],
        'single_import_per_statement' => true,
        'no_extra_blank_lines' => true
    ]);

