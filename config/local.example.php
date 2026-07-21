<?php

declare(strict_types=1);

/**
 * Скопируйте в local.php (дар .gitignore).
 *
 * Supabase: пас аз иҷрои sql/supabase_schema_full.sql дар Dashboard.
 * Парол: Supabase → Project Settings → Database → Database password
 */
return [
    // --- Supabase (тавсия: ҳамчунин .env истифода баред) ---
    'db' => [
        'driver' => 'pgsql',
        'host' => 'db.xenelqfppvjyuxnoamme.supabase.co',
        'port' => '5432',
        'name' => 'postgres',
        'user' => 'postgres',
        'pass' => 'YOUR_POSTGRES_PASSWORD',
        'sslmode' => 'require',
        'auto_ensure_schema' => false,
        'auto_ensure_platform_schema' => false,
        'seed_default_admin_if_empty' => true,
        'seed_platform_demo_if_empty' => false,
    ],
    'supabase' => [
        'url' => 'https://xenelqfppvjyuxnoamme.supabase.co',
        'anon_key' => 'sb_publishable_YOUR_KEY_HERE',
        'project_ref' => 'xenelqfppvjyuxnoamme',
    ],

    // --- MySQL (XAMPP) — комментар кунед, агар Supabase истифода мебаред ---
    // 'db' => [
    //     'driver' => 'mysql',
    //     'host' => '127.0.0.1',
    //     'port' => '3306',
    //     'name' => 'innovaauto',
    //     'user' => 'root',
    //     'pass' => '',
    // ],

    'app' => [
        'base_url' => 'http://localhost/Auto%201',
        'show_dev_login_hint' => true,
    ],
];
