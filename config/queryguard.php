<?php

return [
    'baseline_path' => function_exists('base_path')
        ? base_path('tests/.queryguard-baseline.json')
        : getcwd().'/tests/.queryguard-baseline.json',

    'tolerance' => [
        'extra_queries' => 2,
        'extra_duration_ms' => 50,
    ],

    'n_plus_one' => [
        'threshold' => 2,
    ],

    'slow_query' => [
        'threshold_ms' => 100,
    ],

    'ignore' => [
        'signatures' => [
            // 'select * from migrations%',
        ],
        'tests' => [
            // 'Tests\\Unit\\*',
        ],
    ],

    'reporter' => function_exists('env') ? env('QUERYGUARD_REPORTER', 'console') : 'console',

    'fail_on_regression' => true,
];
