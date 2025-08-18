<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$dbname = "campus_connect_portal";
$username = "root";
$password = "";

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Delete student
$stmt = $pdo->prepare("DELETE FROM students WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);

session_destroy();
session_start();
$_SESSION['message'] = "Profile deleted successfully!";
$_SESSION['message_type'] = "success";
header("Location: login.php");
exit();
