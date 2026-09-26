<?php

declare(strict_types=1);

use Medoo\Medoo;
use Yawasla\Core\Schema;

return function (Medoo $db): void {
    $options = Schema::tableOptions($db);

    $db->create('organizations', [
        '@id',
        'name' => ['VARCHAR(255)', 'NOT NULL'],
        'address' => ['VARCHAR(255)', 'NULL'],
        'city' => ['VARCHAR(255)', 'NULL'],
        'postal_code' => ['VARCHAR(20)', 'NULL'],
        'phone' => ['VARCHAR(20)', 'NULL'],
        'email' => ['VARCHAR(255)', 'NULL'],
        'domain' => ['VARCHAR(255)', 'NULL'],
        'UNIQUE (domain)',
    ], $options);

    $db->create('users', [
        '@id',
        'organization_id' => ['BIGINT', 'NOT NULL'],
        'first_name' => ['VARCHAR(100)', 'NOT NULL'],
        'last_name' => ['VARCHAR(100)', 'NOT NULL'],
        'display_name' => ['VARCHAR(100)', 'NOT NULL'],
        'email' => ['VARCHAR(255)', 'NOT NULL'],
        'password' => ['VARCHAR(255)', 'NOT NULL'],
        'role' => ['VARCHAR(20)', 'NOT NULL'],
        'UNIQUE (email)',
        'FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE',
    ], $options);

    $db->create('announcements', [
        '@id',
        'organization_id' => ['BIGINT', 'NOT NULL'],
        'author_id' => ['BIGINT', 'NULL'],
        'type' => ['VARCHAR(50)', 'NOT NULL'],
        'title' => ['VARCHAR(255)', 'NOT NULL'],
        'content' => ['TEXT', 'NOT NULL'],
        'status' => ['VARCHAR(20)', 'NOT NULL', "DEFAULT 'draft'"],
        'published_at' => ['DATETIME', 'NULL'],
        'FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE',
        'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
    ], $options);

    $db->create('media', [
        '@id',
        'organization_id' => ['BIGINT', 'NOT NULL'],
        'author_id' => ['BIGINT', 'NULL'],
        'title' => ['VARCHAR(255)', 'NOT NULL'],
        'description' => ['TEXT', 'NULL'],
        'media_path' => ['VARCHAR(255)', 'NOT NULL'],
        'status' => ['VARCHAR(20)', 'NOT NULL', "DEFAULT 'draft'"],
        'published_at' => ['DATETIME', 'NULL'],
        'FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE',
        'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
    ], $options);
};
