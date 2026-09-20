<?php

declare(strict_types=1);

use Medoo\Medoo;

return function (Medoo $db): void {
    $db->create('organizations', [
        '@id',
        'nom' => ['VARCHAR(255)', 'NOT NULL'],
        'adresse' => ['VARCHAR(255)', 'NULL'],
        'ville' => ['VARCHAR(255)', 'NULL'],
        'code_postal' => ['VARCHAR(20)', 'NULL'],
        'telephone' => ['VARCHAR(20)', 'NULL'],
        'email' => ['VARCHAR(255)', 'NULL'],
        'domain' => ['VARCHAR(255)', 'NULL'],
        'UNIQUE (domain)',
    ]);

    $db->create('users', [
        '@id',
        'organization_id' => ['BIGINT', 'NOT NULL'],
        'email' => ['VARCHAR(255)', 'NOT NULL'],
        'password' => ['VARCHAR(255)', 'NOT NULL'],
        'role' => ['VARCHAR(20)', 'NOT NULL'],
        'UNIQUE (email)',
        'FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE',
    ]);

    $db->create('announcements', [
        '@id',
        'organization_id' => ['BIGINT', 'NOT NULL'],
        'author_id' => ['BIGINT', 'NULL'],
        'type' => ['VARCHAR(50)', 'NOT NULL'],
        'title' => ['VARCHAR(255)', 'NOT NULL'],
        'content' => ['TEXT', 'NOT NULL'],
        'status' => ['VARCHAR(20)', 'NOT NULL'],
        'published_at' => ['DATETIME', 'NULL'],
        'FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE',
        'FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
    ]);

    $db->create('media', [
        '@id',
        'organization_id' => ['BIGINT', 'NOT NULL'],
        'title' => ['VARCHAR(255)', 'NOT NULL'],
        'description' => ['TEXT', 'NULL'],
        'audio_path' => ['VARCHAR(255)', 'NOT NULL'],
        'status' => ['VARCHAR(20)', 'NOT NULL'],
        'published_at' => ['DATETIME', 'NULL'],
        'FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE ON UPDATE CASCADE',
    ]);
};
