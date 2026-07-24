<?php
// Kopieer dit bestand naar config.php op de server en vul je eigen
// one.com MySQL credentials in. config.php staat NIET in version control.

return [
    'db_host'     => 'localhost',
    'db_name'     => 'VUL_IN',
    'db_user'     => 'VUL_IN',
    'db_password' => 'VUL_IN',
    'db_charset'  => 'utf8mb4',

    'uploads_dir' => __DIR__ . '/../uploads',
    'uploads_url' => 'uploads',

    'session_name'   => 'PE_SESSION',
    'remember_days'  => 30,

    'max_upload_size' => 10 * 1024 * 1024,
];
