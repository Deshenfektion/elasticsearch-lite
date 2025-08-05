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

    'ranking' => [
        'model' => Env::get('RANKING_MODEL', 'bm25'),
        'bm25' => [
            'k1' => Env::get('BM25_K1', 1.2),
            'b' => Env::get('BM25_B', 0.75),
        ],
        'tfidf' => [
            'length_normalisation' => true,
        ],
        'phrase_boost' => 2.0,
        'coordination' => true,
    ],

    'search' => [
        'default_operator' => Env::get('DEFAULT_OPERATOR', 'or'),
        'default_size' => 10,
        'max_size' => 100,
        'max_expansions' => Env::get('SEARCH_MAX_EXPANSIONS', 64),
        'log_queries' => Env::get('SEARCH_LOG_QUERIES', true),
        'cache' => [
            'terms' => 8192,
            'postings' => 512,
            'results' => [
                'enabled' => Env::get('SEARCH_RESULT_CACHE', true),
                'entries' => 256,
                'ttl' => 30,
            ],
        ],
    ],

    'highlight' => [
        'pre_tag' => '<mark>',
        'post_tag' => '</mark>',
        'fragment_size' => 180,
        'max_fragments' => 3,
        'separator' => ' … ',
    ],

    'indexing' => [
        'batch_size' => 250,
        'store_positions' => true,
    ],

    'suggest' => [
        'size' => 8,
        'min_prefix' => 2,
    ],

    'api' => [
        'cors_origin' => Env::get('CORS_ORIGIN', '*'),
        'max_body_bytes' => 2 * 1024 * 1024,
    ],
];
