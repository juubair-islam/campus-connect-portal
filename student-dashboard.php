<?php
session_start();

// If user not logged in or role is not student → redirect to login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.html");
    exit();
}

// DB connection
$host = "localhost";
$dbname = "campus_connect_portal";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch student info
$stmt = $pdo->prepare("SELECT iub_id, name, department, major, minor, email, contact_number, role, created_at
                       FROM students
                       WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// If no student found (shouldn’t happen normally)
if (!$student) {
    session_destroy();
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Campus Connect</title>
    <link rel="stylesheet" href="css/student.css">
</head>
<body>
<header class="header">
    <div class="logo-title">
        <img src="images/logo.png" alt="Campus Connect Logo" class="logo">
        <div class="header-text">
            <h1>Campus Connect</h1>
            <p class="tagline">Welcome, <?php echo htmlspecialchars($student['name']); ?>!</p>
        </div>
    </div>
    <a href="logout.php" class="back-button">🚪 Logout</a>
</header>

<main class="dashboard">
    <h2>📋 Student Profile</h2>
    <div class="profile-card">
        <p><strong>Name:</strong> <?php echo htmlspecialchars($student['name']); ?></p>
        <p><strong>IUB ID:</strong> <?php echo htmlspecialchars($student['iub_id']); ?></p>
        <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department']); ?></p>
        <p><strong>Major:</strong> <?php echo htmlspecialchars($student['major']); ?></p>
        <p><strong>Minor:</strong> <?php echo htmlspecialchars($student['minor']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
        <p><strong>Contact:</strong> <?php echo htmlspecialchars($student['contact_number']); ?></p>
        <p><strong>Role:</strong> <?php echo htmlspecialchars($student['role']); ?></p>
        <p><strong>Joined On:</strong> <?php echo date("F j, Y", strtotime($student['created_at'])); ?></p>
    </div>

    <div class="dashboard-links">
        <a href="#">📦 Lost & Found</a>
        <a href="#">📹 CCTV Reporting</a>
        <a href="#">📅 Event Booking</a>
        <a href="#">🎓 Tutor/Learner Panel</a>
    </div>
</main>

<footer class="footer">
    <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>
</body>
</html>
