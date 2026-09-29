<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\Finder\Finder;

/**
 * Brief §5 / Phase 4 item 5: every Eloquent model must have a matching Policy so access to it is a
 * deliberate decision, not an oversight. Fails loudly (naming the model) the moment a new model is
 * added without one, rather than silently defaulting to "no policy = no restriction".
 */
it('has a policy class for every eloquent model', function () {
    $modelsPath = app_path('Models');
    $finder = (new Finder)->files()->in($modelsPath)->name('*.php');

    $modelsWithoutPolicies = [];

    foreach ($finder as $file) {
        $class = 'App\\Models\\' . $file->getBasename('.php');

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            continue;
        }

        $instance = new $class;

        if (Gate::getPolicyFor($instance) === null) {
            $modelsWithoutPolicies[] = $class;
        }
    }

    expect($modelsWithoutPolicies)->toBe([]);
});
