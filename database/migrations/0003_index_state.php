<?php

declare(strict_types=1);

return [
    'mysql' => [
        'CREATE TABLE index_state (
            stat_key VARCHAR(64) NOT NULL,
            stat_value BIGINT NOT NULL DEFAULT 0,
            PRIMARY KEY (stat_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
    ],

    'sqlite' => [
        'CREATE TABLE index_state (
            stat_key VARCHAR(64) NOT NULL PRIMARY KEY,
            stat_value INTEGER NOT NULL DEFAULT 0
        )',
    ],
];
