<?php
$dsn = 'sqlite:E:\APPS\franco-pay\database\franco_pay.sqlite';
$db = new PDO($dsn);
$row = $db->query("SELECT email, password_hash FROM users WHERE email = 'francis@example.test'")->fetch();
var_dump($row);
