<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method allowed']);
    exit;
}

$host = "localhost";
$dbname = "campus_connect_portal";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: '.$e->getMessage()]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['role'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data']);
    exit;
}

function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$role = $data['role'];

if ($role === 'student') {
    $required = ['iub_id', 'name', 'email', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            exit;
        }
    }

    $iub_id         = trim($data['iub_id']);
    $name           = trim($data['name']);
    $department     = !empty($data['department']) ? trim($data['department']) : '';
    $major          = !empty($data['major']) ? trim($data['major']) : '';
    $minor          = !empty($data['minor']) ? trim($data['minor']) : '';
    $email          = trim($data['email']);
    $contact_number = !empty($data['contact_number']) ? trim($data['contact_number']) : '';
    $password       = $data['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        exit;
    }

    // Check duplicate IUB ID or email
    $stmt = $pdo->prepare("SELECT id FROM students WHERE iub_id = ? OR email = ?");
    $stmt->execute([$iub_id, $email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Student with this IUB ID or email already exists']);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $uid = generateUUID();

    try {
        $stmt = $pdo->prepare("INSERT INTO students (uid, iub_id, name, department, major, minor, email, contact_number, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'student')");
        $stmt->execute([$uid, $iub_id, $name, $department, $major, $minor, $email, $contact_number, $passwordHash]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create student account: '.$e->getMessage()]);
    }
} elseif ($role === 'administrative_staff') {
    $required = ['full_name', 'employee_id', 'department', 'iub_email', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            exit;
        }
    }

    $full_name      = trim($data['full_name']);
    $employee_id    = trim($data['employee_id']);
    $department     = !empty($data['department']) ? trim($data['department']) : '';
    $iub_email      = trim($data['iub_email']);
    $contact_number = !empty($data['contact_number']) ? trim($data['contact_number']) : '';
    $password       = $data['password'];

    if (!filter_var($iub_email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        exit;
    }

    // Check duplicate employee ID or email
    $stmt = $pdo->prepare("SELECT id FROM administrative_staff WHERE employee_id = ? OR iub_email = ?");
    $stmt->execute([$employee_id, $iub_email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Staff with this Employee ID or IUB email already exists']);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $uid = generateUUID();

    try {
        $stmt = $pdo->prepare("INSERT INTO administrative_staff (uid, full_name, employee_id, department, contact_number, iub_email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'administrative_staff')");
        $stmt->execute([$uid, $full_name, $employee_id, $department, $contact_number, $iub_email, $passwordHash]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create staff account: '.$e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role']);
}
