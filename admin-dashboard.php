<?php
session_start();

// Allow only admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

// Since your admin login is hardcoded and session 'name' is 'Admin', you might not have admin details in DB.
// But if you want, you can create an admins table and fetch details here.
// For now, just use session name.
$adminName = $_SESSION['name'] ?? 'Admin';

// Extract first name for greeting
$firstName = explode(' ', trim($adminName))[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard - Campus Connect</title>
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
    <span class="user-name"><?php echo htmlspecialchars($adminName); ?></span>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</header>

<nav class="top-nav">
  <a href="#">👤 Profile</a>
  <a href="#">📋 Manage Users</a>
  <a href="#">📊 Reports</a>
  <a href="#">⚙️ Settings</a>
  <!-- Add more admin-specific links -->
</nav>

<main class="dashboard">
  <section class="activity-gist">
    <h2>📊 <?php echo htmlspecialchars($firstName); ?>'s Admin Overview</h2>
    <div class="activity-cards">
      <div class="activity-card">
        <h3>User Registrations</h3>
        <p><strong>15</strong> new users registered this week.</p>
      </div>
      <div class="activity-card">
        <h3>Pending Approvals</h3>
        <p><strong>4</strong> requests waiting for approval.</p>
      </div>
      <div class="activity-card">
        <h3>System Alerts</h3>
        <p><strong>2</strong> critical alerts issued.</p>
      </div>
      <div class="activity-card">
        <h3>Announcements</h3>
        <p><strong>3</strong> new announcements posted.</p>
      </div>
    </div>
  </section>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
