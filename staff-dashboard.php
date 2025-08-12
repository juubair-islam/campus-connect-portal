<?php
session_start();

// Correct role check: administrative_staff or admin allowed
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'administrative_staff' && $_SESSION['role'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

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

// Fetch staff info by session user_id
$stmt = $pdo->prepare("SELECT uid, full_name, employee_id, department, contact_number, iub_email, role, created_at 
                       FROM administrative_staff 
                       WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Extract first name for greeting
$firstName = explode(' ', trim($staff['full_name']))[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Staff Dashboard - Campus Connect</title>
  <link rel="stylesheet" href="css/student.css" />
</head>
<body>

<header class="header">
  <div class="header-left">
    <img src="images/logo.png" alt="Campus Connect Logo" class="logo" />
    <div class="title-text">
      <h1>Campus Connect</h1>
      <p class="tagline">Bridge to Your IUB Community</p>
    </div>
  </div>
  <div class="header-right">
    <span class="user-name"><?php echo htmlspecialchars($staff['full_name']); ?></span>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</header>

<nav class="top-nav">
  <a href="#">📋 Profile</a>
  <a href="#">🏷️ Lost &amp; Found</a>
  <a href="#">📹 CCTV Reporting</a>
  <a href="#">📅 Event Booking</a>
  <!-- Add any staff-specific links if needed -->
</nav>

<main class="dashboard">
  <section class="activity-gist">
    <h2>📊 <?php echo htmlspecialchars($firstName); ?>'s Recent Activity</h2>
    <div class="activity-cards">
      <div class="activity-card">
        <h3>Administrative Tasks</h3>
        <p>You have <strong>3</strong> tasks pending.</p>
      </div>
      <div class="activity-card">
        <h3>Reports to Review</h3>
        <p><strong>2</strong> reports waiting for your approval.</p>
      </div>
      <div class="activity-card">
        <h3>Meetings Scheduled</h3>
        <p>You have <strong>4</strong> meetings this week.</p>
      </div>
      <div class="activity-card">
        <h3>Announcements</h3>
        <p><strong>1</strong> new announcement posted.</p>
      </div>
    </div>
  </section>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
