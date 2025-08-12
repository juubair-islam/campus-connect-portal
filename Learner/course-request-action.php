<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? '';
$course_id = $input['course_id'] ?? 0;

if (!$course_id || !is_numeric($course_id) || !in_array($action, ['send', 'cancel'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
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
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

// Get current user's UID
$stmt = $pdo->prepare("SELECT uid FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$uid = $user['uid'];

try {
    if ($action === 'send') {
        // Check if already requested or enrolled
        $checkEnroll = $pdo->prepare("SELECT 1 FROM course_enrollments WHERE course_id = ? AND learner_uid = ?");
        $checkEnroll->execute([$course_id, $uid]);
        if ($checkEnroll->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You are already enrolled in this course.']);
            exit;
        }
        $checkReq = $pdo->prepare("SELECT status FROM course_requests WHERE course_id = ? AND learner_uid = ?");
        $checkReq->execute([$course_id, $uid]);
        if ($existing = $checkReq->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'message' => 'Request already exists with status: ' . $existing['status']]);
            exit;
        }

        // Insert new request
        $insert = $pdo->prepare("INSERT INTO course_requests (course_id, learner_uid, status) VALUES (?, ?, 'pending')");
        $insert->execute([$course_id, $uid]);
        echo json_encode(['success' => true, 'message' => 'Request sent successfully.']);
        exit;
    } elseif ($action === 'cancel') {
        // Only allow canceling pending requests
        $del = $pdo->prepare("DELETE FROM course_requests WHERE course_id = ? AND learner_uid = ? AND status = 'pending'");
        $del->execute([$course_id, $uid]);
        if ($del->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Request canceled successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No pending request to cancel.']);
        }
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
