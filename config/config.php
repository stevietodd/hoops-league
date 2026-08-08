<?php

declare(strict_types=1);

/**
 * Copy to config.local.php and adjust for your environment.
 * config.local.php is gitignored if you prefer; otherwise edit values here for local use.
 */

return [
    'debug' => true,
    'app_name' => 'Hoops League',
    'base_url' => '', // e.g. '' when site is at domain root, or '/hoops' if in a subfolder
    'timezone' => 'America/New_York',
    'db_path' => dirname(__DIR__) . '/data/hoops.sqlite3',
];
