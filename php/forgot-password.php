<?php
header('Content-Type: application/json');

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// DB connection - change creds as needed
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

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$action = $data['action'] ?? '';

if ($action === 'verify') {
    $userId = trim($data['userId'] ?? '');
    $userContact = trim($data['userContact'] ?? '');

    if (!$userId || !$userContact) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing ID or contact info']);
        exit;
    }

    // Search in students table
    $stmt = $pdo->prepare("SELECT iub_id AS userId, email, contact_number, role FROM students WHERE iub_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Search in administrative_staff table
        $stmt = $pdo->prepare("SELECT employee_id AS userId, iub_email AS email, contact_number, role FROM administrative_staff WHERE employee_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User ID not found']);
        exit;
    }

    // Check if email or contact matches
    if (strcasecmp($userContact, $user['email']) !== 0 && strcasecmp($userContact, $user['contact_number']) !== 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Contact info does not match']);
        exit;
    }

    // Return success with role & userId for next step
    echo json_encode(['success' => 'User verified', 'user' => ['userId' => $user['userId'], 'role' => $user['role']]]);
    exit;

} elseif ($action === 'reset') {
    $userId = trim($data['userId'] ?? '');
    $role = $data['role'] ?? '';
    $newPassword = $data['newPassword'] ?? '';

    if (!$userId || !$role || !$newPassword) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    // Hash password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    if ($role === 'student') {
        $stmt = $pdo->prepare("UPDATE students SET password = ? WHERE iub_id = ?");
        $stmt->execute([$passwordHash, $userId]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => 'Password updated successfully']);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Password update failed']);
            exit;
        }
    } elseif ($role === 'administrative_staff') {
        $stmt = $pdo->prepare("UPDATE administrative_staff SET password = ? WHERE employee_id = ?");
        $stmt->execute([$passwordHash, $userId]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => 'Password updated successfully']);
            exit;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Password update failed']);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid role']);
        exit;
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}
?>
