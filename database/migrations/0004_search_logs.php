<?php

declare(strict_types=1);

return [
    'mysql' => [
        'CREATE TABLE search_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            query VARCHAR(512) NOT NULL,
            normalised_query VARCHAR(191) NOT NULL,
            filters TEXT NULL,
            hit_count INT UNSIGNED NOT NULL DEFAULT 0,
            took_us INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_search_logs_normalised (normalised_query),
            KEY idx_search_logs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
    ],

    'sqlite' => [
        'CREATE TABLE search_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            query VARCHAR(512) NOT NULL,
            normalised_query VARCHAR(191) NOT NULL,
            filters TEXT NULL,
            hit_count INTEGER NOT NULL DEFAULT 0,
            took_us INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        )',
        'CREATE INDEX idx_search_logs_normalised ON search_logs (normalised_query)',
        'CREATE INDEX idx_search_logs_created_at ON search_logs (created_at)',
    ],
];
