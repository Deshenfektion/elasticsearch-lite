<?php

declare(strict_types=1);

return [
    'mysql' => [
        'CREATE TABLE categories (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(128) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_categories_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',

        'CREATE TABLE tags (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(128) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_tags_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',

        'CREATE TABLE documents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            external_id VARCHAR(191) NOT NULL,
            media_type VARCHAR(64) NOT NULL,
            title VARCHAR(512) NOT NULL,
            body MEDIUMTEXT NOT NULL,
            url VARCHAR(512) NULL,
            author VARCHAR(191) NULL,
            category_id BIGINT UNSIGNED NULL,
            published_at DATETIME NULL,
            checksum CHAR(40) NOT NULL,
            token_count INT UNSIGNED NOT NULL DEFAULT 0,
            indexed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_documents_external_id (external_id),
            KEY idx_documents_author (author),
            KEY idx_documents_category (category_id, published_at),
            KEY idx_documents_published_at (published_at),
            CONSTRAINT fk_documents_category FOREIGN KEY (category_id)
                REFERENCES categories (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',

        'CREATE TABLE document_tags (
            document_id BIGINT UNSIGNED NOT NULL,
            tag_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (document_id, tag_id),
            KEY idx_document_tags_tag (tag_id, document_id),
            CONSTRAINT fk_document_tags_document FOREIGN KEY (document_id)
                REFERENCES documents (id) ON DELETE CASCADE,
            CONSTRAINT fk_document_tags_tag FOREIGN KEY (tag_id)
                REFERENCES tags (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
    ],

    'sqlite' => [
        'CREATE TABLE categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(128) NOT NULL
        )',
        'CREATE UNIQUE INDEX uq_categories_name ON categories (name)',

        'CREATE TABLE tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(128) NOT NULL
        )',
        'CREATE UNIQUE INDEX uq_tags_name ON tags (name)',

        'CREATE TABLE documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            external_id VARCHAR(191) NOT NULL,
            media_type VARCHAR(64) NOT NULL,
            title VARCHAR(512) NOT NULL,
            body TEXT NOT NULL,
            url VARCHAR(512) NULL,
            author VARCHAR(191) NULL,
            category_id INTEGER NULL REFERENCES categories (id) ON DELETE SET NULL,
            published_at DATETIME NULL,
            checksum CHAR(40) NOT NULL,
            token_count INTEGER NOT NULL DEFAULT 0,
            indexed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )',
        'CREATE UNIQUE INDEX uq_documents_external_id ON documents (external_id)',
        'CREATE INDEX idx_documents_author ON documents (author)',
        'CREATE INDEX idx_documents_category ON documents (category_id, published_at)',
        'CREATE INDEX idx_documents_published_at ON documents (published_at)',

        'CREATE TABLE document_tags (
            document_id INTEGER NOT NULL REFERENCES documents (id) ON DELETE CASCADE,
            tag_id INTEGER NOT NULL REFERENCES tags (id) ON DELETE CASCADE,
            PRIMARY KEY (document_id, tag_id)
        )',
        'CREATE INDEX idx_document_tags_tag ON document_tags (tag_id, document_id)',
    ],
];
