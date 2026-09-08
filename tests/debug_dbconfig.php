<?php
require __DIR__ . '/../src/Config/env.php';
var_dump(getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_NAME'), getenv('DB_USER'));
var_dump(App\Config\Database::getConfig());
