<?php

// SPDX-License-Identifier: Apache-2.0
// Cấu hình kết nối cơ sở dữ liệu PostgreSQL 17 tích hợp pgvector & unaccent

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'postgres'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'acc'),
            'username' => env('DB_USERNAME', 'acc'),
            'password' => env('DB_PASSWORD', 'acc'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
];
