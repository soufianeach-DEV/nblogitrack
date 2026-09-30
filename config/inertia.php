<?php

return [

    // Les pages React sont dans resources/js/Pages, avec une majuscule.
    // Inertia 3 cherche par defaut resources/js/pages : sur un disque qui
    // distingue les majuscules, comme en CI et sur Render, les tests qui
    // verifient qu'une page existe echouaient tous.
    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [
            resource_path('js/Pages'),
        ],
        'extensions' => ['jsx'],
    ],

    'testing' => [
        'ensure_pages_exist' => true,
    ],

    // Les outils de developpement d'Inertia 3 s'allument d'eux-memes en
    // local et gardent les requetes dans storage/inertia-devtools, un
    // dossier que le depot n'ignore pas. Ils restent eteints tant que
    // INERTIA_DEVTOOLS_ENABLED ne vaut pas true.
    'devtools' => [
        'enabled' => (bool) env('INERTIA_DEVTOOLS_ENABLED', false),
        'except' => ['telescope*', 'horizon*', '_inertia/devtools*'],
        'storage' => [
            'path' => storage_path('inertia-devtools'),
            'ttl' => (int) env('INERTIA_DEVTOOLS_TTL_HOURS', 24),
            'prune_interval' => (int) env('INERTIA_DEVTOOLS_PRUNE_INTERVAL_SECONDS', 300),
            'limit' => (int) env('INERTIA_DEVTOOLS_LIMIT', 100),
        ],
        'middleware' => ['web'],
        'gate' => env('INERTIA_DEVTOOLS_GATE'),
        'redact' => [
            'keys' => [
                'password',
                'password_confirmation',
                'current_password',
                'token',
                '_token',
                'access_token',
                'refresh_token',
                'secret',
                'client_secret',
                'api_key',
            ],
            'headers' => [
                'cookie',
                'set-cookie',
                'authorization',
                'proxy-authorization',
                'x-xsrf-token',
                'x-csrf-token',
            ],
        ],
    ],

];
