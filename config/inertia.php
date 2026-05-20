<?php

return [
    'ssr' => [
        'enabled' => false,
    ],
    'history' => [
        'encrypt' => false,
    ],
    'version' => fn () => null,
    'pages' => [
        'paths' => [
            resource_path('js/Pages'),
        ],
        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],
    ],
];
