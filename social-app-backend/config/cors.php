<?php

return [

    /*
    | CORS configuration tuned for the Sakai React frontend.
    |
    | - `paths` covers the entire /api/* surface, including the v1 prefix.
    | - `allowed_origins` lists the dev origins explicitly so we can set
    |   `supports_credentials` to true (the wildcard "*" + credentials is
    |   rejected by browsers).
    | - `exposed_headers` lets the frontend read rate-limit / pagination
    |   headers if we add them later.
    */

    'paths' => ['api/*', 'docs/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_URL', 'http://localhost:3000')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
