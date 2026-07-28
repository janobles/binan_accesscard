<?php

use CodeIgniter\CodingStandard\CodeIgniter4;
use Nexus\CsConfig\Factory;
use PhpCsFixer\Finder;

// Formatting only. Every rule here must leave the executable token stream
// untouched, because scripts/assert-tokens-unchanged.php is run against this
// config's output. A rule that trips that gate gets removed rather than
// accommodated.
//
// app/Views is excluded because php-cs-fixer is unreliable on files that are
// mostly inline HTML. app/Config is excluded because those are stock CI4
// files we do not own.
$finder = Finder::create()
    ->files()
    ->name('*.php')
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/tests',
        __DIR__ . '/tools',
    ])
    ->exclude([
        'Config',
        'Views',
    ])
    ->notName('Common.php')
    ->append([__FILE__]);

$overrides = [];

$options = [
    'finder'       => $finder,
    'cacheFile'    => 'writable/cache/.php-cs-fixer.cache',
    'usingCache'   => true,
    'customFixers' => [],
];

return Factory::create(new CodeIgniter4(), $overrides, $options)->forProjects();
