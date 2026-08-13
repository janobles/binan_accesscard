#!/usr/bin/env php
<?php

/**
 * Fails when a route names a controller class or method that does not exist.
 *
 * `php spark routes` builds and lists the route table, so it catches a config
 * that throws during registration or a class that cannot be loaded then. It
 * never checks that the handler method exists: a route pointed at a missing
 * method lists fine and 500s on the first request. This walks the built table
 * and reflects over each handler instead.
 *
 * Closure routes and routes handled by a filter-only entry are skipped: there
 * is no class to reflect over.
 *
 * A method that exists but is not public also counts as a problem. CodeIgniter
 * dispatches a route with `is_callable([$controller, $method], false)`, which
 * is false for a protected or private method from outside the class, so a
 * route pointed at one 404s at request time the same as a missing method.
 */

require __DIR__ . '/../vendor/autoload.php';

$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/Boot.php';

// bootWeb() also dispatches and runs the current request, which is a CLI
// invocation with no matching route and throws. bootWorker() runs the same
// setup (autoloader, environment, DI container) and hands back the
// initialized app without dispatching a request, which is what
// FrankenPHP's worker mode needs between requests and what this script
// needs here.
CodeIgniter\Boot::bootWorker($paths);

// bootWorker() wires the DI container but never dispatches a request, so
// nothing has triggered the route file to load yet. bootConsole() takes this
// same extra step for spark subcommands; do it explicitly here too, or
// getRoutes() silently returns an empty table and every check below passes
// for the wrong reason.
$routes = Config\Services::routes();
$routes->loadRoutes();
$problems = [];

// RouteCollection stores its table keyed by uppercase HTTP verb ('GET', not
// 'get'); passing a lowercase verb silently matches nothing and getRoutes()
// returns an empty array instead of erroring.
foreach (['get', 'post', 'put', 'patch', 'delete', 'options', 'cli'] as $verb) {
    foreach ($routes->getRoutes(strtoupper($verb)) as $from => $handler) {
        if (! is_string($handler) || ! str_contains($handler, '::')) {
            continue;
        }

        [$class, $method] = explode('::', $handler, 2);
        $method = explode('/', $method, 2)[0];

        if (! class_exists($class)) {
            $problems[] = sprintf('%s %s -> class %s does not exist', strtoupper($verb), $from, $class);
            continue;
        }

        if (! method_exists($class, $method)) {
            $problems[] = sprintf('%s %s -> %s has no method %s()', strtoupper($verb), $from, $class, $method);
            continue;
        }

        $reflection = new ReflectionMethod($class, $method);

        if (! $reflection->isPublic()) {
            $problems[] = sprintf('%s %s -> %s::%s() is not public', strtoupper($verb), $from, $class, $method);
        }
    }
}

if ($problems !== []) {
    fwrite(STDERR, "Routes with a missing handler:\n");
    foreach ($problems as $problem) {
        fwrite(STDERR, '  ' . $problem . "\n");
    }

    exit(1);
}

echo 'OK: every route handler resolves.', PHP_EOL;
exit(0);
