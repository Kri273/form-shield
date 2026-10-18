<?php

$config = require __DIR__ . '/../config/config.php';

$username = readline('Admin username: ');
$password = readline('Admin password: ');

$db = $config['database'];

$pdo = new PDO(
    "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}",
    $db['username'],
    $db['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$stmt = $pdo->prepare(
    'INSERT INTO admins (username, password_hash)
     VALUES (:username, :password_hash)'
);

$stmt->execute([
    'username' => $username,
    'password_hash' => $passwordHash
]);

echo 'Admin created successfully.' . PHP_EOL;