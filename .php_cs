<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
;

return PhpCsFixer\Config::create()
    ->setRules([
        '@PSR2' => true,
        'braces' => ['position_after_functions_and_oop_constructs' => 'same'],
    ])
    ->setFinder($finder)
;