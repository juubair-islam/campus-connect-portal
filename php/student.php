<?php
session_start();
header('Content-Type: application/json');

// Check login session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// DB connect
$host = "localhost";
$dbname = "campus_connect_portal";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

// Get student by IUB ID
$iub_id = $_SESSION['id'];

$stmt = $pdo->prepare("SELECT name, iub_id, department, major, minor FROM students WHERE iub_id = ?");
$stmt->execute([$iub_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo json_encode(['success' => false, 'error' => 'Student not found']);
    exit;
}

echo json_encode(['success' => true] + $student);
exit;
