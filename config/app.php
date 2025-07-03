<?php

declare(strict_types=1);

use EsLite\Support\Env;

return [
    'environment' => Env::get('ES_LITE_ENV', 'local'),

    'database' => [
        'driver' => Env::get('DB_DRIVER', 'mysql'),
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::get('DB_PORT', 3306),
        'database' => Env::get('DB_DATABASE', 'eslite'),
        'username' => Env::get('DB_USERNAME', 'eslite'),
        'password' => Env::get('DB_PASSWORD', 'eslite'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_0900_ai_ci',
    ],

    'analysis' => [
        'stopwords' => Env::get('STOPWORDS', 'english'),
        'stemmer' => Env::get('STEMMER', 'porter'),
        'ascii_folding' => Env::get('ASCII_FOLDING', true),
        'min_token_length' => 2,
        'max_token_length' => 40,
    ],

    'fields' => [
        'title' => ['id' => 1, 'boost' => Env::get('FIELD_BOOST_TITLE', 3.0)],
        'tags' => ['id' => 2, 'boost' => Env::get('FIELD_BOOST_TAGS', 2.0)],
        'body' => ['id' => 3, 'boost' => Env::get('FIELD_BOOST_BODY', 1.0)],
    ],

    'indexing' => [
        'batch_size' => 250,
        'store_positions' => true,
    ],
];
