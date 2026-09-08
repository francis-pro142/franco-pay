<?php
require __DIR__ . '/../vendor/autoload.php';
$db = App\Services\DatabaseConnection::get();
$row = $db->query("SELECT id, email, password_hash FROM users WHERE email = 'francis@example.test'")->fetch();
var_dump($row);
if ($row) {
    var_dump(password_verify('password123', $row['password_hash']));
}
