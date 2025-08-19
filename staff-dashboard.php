<?php
session_start();

// Only administrative_staff or admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'administrative_staff' && $_SESSION['role'] !== 'admin')) {
    header("Location: ../login.php");
    exit();
}

// DB Connection
$host = "localhost";
$dbname = "campus_connect_portal";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

// Staff info
$stmt = $pdo->prepare("SELECT * FROM administrative_staff WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

$firstName = explode(' ', trim($staff['full_name']))[0];

// Recent Activity - Last 5 found items reported by this staff
$recentFound = $pdo->prepare("SELECT item_name, created_at, status FROM found_items WHERE finder_uid = ? ORDER BY created_at DESC LIMIT 5");
$recentFound->execute([$_SESSION['user_id']]);
$recentFound = $recentFound->fetchAll(PDO::FETCH_ASSOC);

// Fetch announcements
$announcementStmt = $pdo->query("
    SELECT title, content, created_at
    FROM announcements
    ORDER BY created_at DESC
    LIMIT 5
");
$announcements = $announcementStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff Dashboard - Campus Connect</title>
<link rel="stylesheet" href="css/student.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
main.dashboard { max-width:1200px; margin:30px auto 60px auto; padding:0 20px; display:flex; flex-direction:column; gap:30px; color:#1e3a5f; }

/* Top nav */
nav.top-nav { display: flex; background: #e5f4fc; padding: 10px 20px; flex-wrap: wrap; }
nav.top-nav a { margin-right: 15px; text-decoration: none; padding: 8px 12px; color: #007cc7; font-weight: bold; border-radius: 5px; transition: 0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background: #007cc7; color: #fff; }

/* Profile snapshot */
.profile-snapshot { background:#e5f4fc; padding:20px; border-radius:15px; box-shadow:0 4px 12px rgba(0,124,199,0.1); }
.profile-snapshot h3 { margin:0 0 10px; color:#007cc7; }
.profile-snapshot p { margin:4px 0; }

/* Recent Activity & Announcements */
.card { background:white; padding:20px; border-radius:15px; box-shadow:0 6px 14px rgba(0,0,0,0.08); margin-bottom:20px; }
.card h3 { color:#007cc7; margin-bottom:12px; }
.card ul { list-style:none; margin:0; padding:0; }
.card li { margin-bottom:15px; padding-bottom:10px; border-bottom:1px solid #eee; }
.card li:last-child { border-bottom:none; }
.card li small { color:#555; display:block; margin-bottom:4px; }
.card li p { margin:0; }

/* Announcements list */
.announcements-list { list-style: none; padding: 0; margin: 0; }
.announcements-list li {
    position: relative;
    padding: 15px 20px 15px 35px;
    margin-bottom: 15px;
    border-left: 5px solid #007cc7;
    background: #f9f9f9;
    border-radius: 8px;
}
.announcements-list li:last-child { margin-bottom: 0; }
.announcements-list li small { display: block; margin-bottom: 5px; color: #555; }

/* Responsive */
@media(max-width:768px){
  .grid-2, .quick-links { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<header class="header">
  <div class="header-left">
    <img src="images/logo.png" alt="Campus Connect Logo" class="logo">
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
  <a href="staff-dashboard.php" class="active">Home</a>
  <a href="staff-Profile.php">Profile</a>
  <a href="staff-found-item-report.php">Found Report</a>
  <a href="lost & found/staff-lost-items.php">Lost Item Reports</a>
  <a href="staff-found-items.php">Found Items</a>
</nav>

<main class="dashboard">

  <!-- Profile Snapshot -->
  <div class="profile-snapshot">
    <h3>Profile Info</h3>
    <p><strong>Name:</strong> <?php echo htmlspecialchars($firstName); ?></p>
    <p><strong>Department:</strong> <?php echo htmlspecialchars($staff['department'] ?? 'N/A'); ?></p>
    <p><strong>Email:</strong> <?php echo htmlspecialchars($staff['iub_email'] ?? 'N/A'); ?></p>
    <p><strong>Contact:</strong> <?php echo htmlspecialchars($staff['contact_number'] ?? 'N/A'); ?></p>
  </div>

  <!-- Announcements -->
  <div class="card">
    <h3>Announcements</h3>
    <ul class="announcements-list">
      <?php if($announcements): ?>
          <?php foreach($announcements as $ann): ?>
              <li>
                  <strong><?php echo htmlspecialchars($ann['title']); ?></strong>
                  <small>Posted on <?php echo date("M d, Y H:i", strtotime($ann['created_at'])); ?></small>
                  <p><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
              </li>
          <?php endforeach; ?>
      <?php else: ?>
          <li>No announcements yet.</li>
      <?php endif; ?>
    </ul>
  </div>

  <!-- Recent Activity -->
  <div class="card">
    <h3>Recent Activity</h3>
    <ul>
      <?php if($recentFound): ?>
        <?php foreach($recentFound as $row): ?>
          <li>📦 <?php echo htmlspecialchars($row['item_name']); ?> - <?php echo htmlspecialchars(ucfirst($row['status'])); ?> (<?php echo date("M d, Y", strtotime($row['created_at'])); ?>)</li>
        <?php endforeach; ?>
      <?php else: ?>
        <li>No recent activity.</li>
      <?php endif; ?>
    </ul>
  </div>

</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
