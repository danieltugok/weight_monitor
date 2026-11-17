<?php
// Copy this file to config.php on the server and fill it in.
// config.php is git-ignored: never commit real credentials.
return [
    // From hPanel > Databases > MySQL Databases
    'dsn'     => 'mysql:host=localhost;dbname=YOUR_DATABASE_NAME;charset=utf8mb4',
    'db_user' => 'YOUR_DATABASE_USER',
    'db_pass' => 'YOUR_DATABASE_PASSWORD',

    // The password you type in the app to add/edit weights. At least 8 characters.
    'password' => 'CHANGE_ME_TO_A_LONG_PASSWORD',
];
