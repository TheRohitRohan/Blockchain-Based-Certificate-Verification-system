<?php
require 'vendor/autoload.php';

$db = new PDO('mysql:host=127.0.0.1;dbname=certificate_db', 'root', 'root123');

echo "=== STUDENTS ===\n";
$stmt = $db->query('SELECT id, user_id, student_id FROM students LIMIT 5');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Students found: " . count($rows) . "\n";
foreach($rows as $r) {
    echo "  ID: {$r['id']}, User: {$r['user_id']}, Student: {$r['student_id']}\n";
}

echo "\n=== USERS with email ahmed.rashidi@uot.edu ===\n";
$stmt = $db->prepare('SELECT id, email, role FROM users WHERE email = ?');
$stmt->execute(['ahmed.rashidi@uot.edu']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user) {
    echo "  User ID: {$user['id']}, Email: {$user['email']}, Role: {$user['role']}\n";
} else {
    echo "  User NOT FOUND\n";
}

echo "\n=== UNIVERSITIES ===\n";
$stmt = $db->query('SELECT id, name, code FROM universities LIMIT 3');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $r) {
    echo "  ID: {$r['id']}, Name: {$r['name']}, Code: {$r['code']}\n";
}
