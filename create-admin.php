<?php
require_once __DIR__ . '/config/database.php';

$fullName = 'Your Name';
$email    = 'admin@ironforgegym.com';
$password = 'ChangeThisPassword123';

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO admins (full_name, email, password_hash) VALUES (?, ?, ?)'
);

try {
    $stmt->execute([$fullName, $email, $hash]);
    echo "Admin account created for $email. You can now delete this file.";
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo "An admin with that email already exists.";
    } else {
        throw $e;
    }
}