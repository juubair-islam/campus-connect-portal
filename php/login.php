<?php
session_start(); // ✅ Must be at the top for session to work
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// DB connection parameters
$host = "localhost";
$dbname = "campus_connect_portal";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => "Database connection failed: " . $e->getMessage()]);
    exit;
}

// Read input JSON
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$role = $data['role'] ?? '';
$loginId = trim($data['loginId'] ?? '');
$password = $data['password'] ?? '';

if (!$role || !$loginId || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing login credentials']);
    exit;
}

// Define table and ID field by role
if ($role === 'student') {
    $table = 'students';
    $idField = 'iub_id';
} elseif ($role === 'administrative_staff') {
    $table = 'administrative_staff';
    $idField = 'employee_id';
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role']);
    exit;
}

// Lookup user
$stmt = $pdo->prepare("SELECT * FROM $table WHERE $idField = ?");
$stmt->execute([$loginId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'User not found']);
    exit;
}

// Verify password (stored hashed)
if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Incorrect password']);
    exit;
}

// ✅ Set session variables here
$_SESSION['user_id'] = $user['id']; // assuming table has auto ID field named 'id'
$_SESSION['role'] = $role;

// ✅ Optional: Set other info if you need
$_SESSION['name'] = $role === 'student' ? $user['name'] : $user['full_name'];

// ✅ Return success
echo json_encode([
    'success' => true,
    'role' => $role,
    'name' => $_SESSION['name'],
    'id' => $loginId
]);
exit;
