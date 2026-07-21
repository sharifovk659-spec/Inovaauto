<?php

declare(strict_types=1);

/**
 * Намуна барои PostgreSQL: ин массивро ба `config/local.php` гузоред ё мундариҷаашро ба массиви `return`-и `local.php` омехта кунед.
 *
 * Қиматҳои дар зер холӣ — номи база, логин ва паролро худатон пур кунед.
 * Барои муҳити воқеӣ: `seed_default_admin_if_empty` ва `seed_platform_demo_if_empty`-ро `false` нигоҳ доред
 * ва ягон корбари админро дастӣ эҷод кунед.
 */
return [
    'db' => [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'port' => '5432',
        'name' => '',
        'user' => '',
        'pass' => '',
        'charset' => 'utf8mb4',
        'auto_ensure_schema' => true,
        'auto_ensure_platform_schema' => true,
        'seed_default_admin_if_empty' => false,
        'seed_platform_demo_if_empty' => false,
    ],
];
