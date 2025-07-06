<?php

declare(strict_types=1);

return [
    'mysql' => [
        'CREATE TABLE terms (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            term VARBINARY(128) NOT NULL,
            document_frequency INT NOT NULL DEFAULT 0,
            total_frequency BIGINT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uq_terms_term (term)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',

        'CREATE TABLE postings (
            term_id BIGINT UNSIGNED NOT NULL,
            field_id TINYINT UNSIGNED NOT NULL,
            document_id BIGINT UNSIGNED NOT NULL,
            term_frequency SMALLINT UNSIGNED NOT NULL,
            field_length SMALLINT UNSIGNED NOT NULL,
            positions BLOB NULL,
            PRIMARY KEY (term_id, field_id, document_id),
            KEY idx_postings_document (document_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',

        'CREATE TABLE document_fields (
            document_id BIGINT UNSIGNED NOT NULL,
            field_id TINYINT UNSIGNED NOT NULL,
            length INT UNSIGNED NOT NULL,
            PRIMARY KEY (document_id, field_id),
            CONSTRAINT fk_document_fields_document FOREIGN KEY (document_id)
                REFERENCES documents (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
    ],

    'sqlite' => [
        'CREATE TABLE terms (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            term BLOB NOT NULL,
            document_frequency INTEGER NOT NULL DEFAULT 0,
            total_frequency INTEGER NOT NULL DEFAULT 0
        )',
        'CREATE UNIQUE INDEX uq_terms_term ON terms (term)',

        'CREATE TABLE postings (
            term_id INTEGER NOT NULL,
            field_id INTEGER NOT NULL,
            document_id INTEGER NOT NULL,
            term_frequency INTEGER NOT NULL,
            field_length INTEGER NOT NULL,
            positions BLOB NULL,
            PRIMARY KEY (term_id, field_id, document_id)
        )',
        'CREATE INDEX idx_postings_document ON postings (document_id)',

        'CREATE TABLE document_fields (
            document_id INTEGER NOT NULL REFERENCES documents (id) ON DELETE CASCADE,
            field_id INTEGER NOT NULL,
            length INTEGER NOT NULL,
            PRIMARY KEY (document_id, field_id)
        )',
    ],
];
