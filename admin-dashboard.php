<?php
session_start();

// Only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
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

// Fetch admin info
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
$firstName = explode(' ', trim($admin['full_name']))[0];

// ----------------- Stats -----------------
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalStaff = $pdo->query("SELECT COUNT(*) FROM administrative_staff")->fetchColumn();
$totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalLost = $pdo->query("SELECT COUNT(*) FROM lost_items")->fetchColumn();
$totalFound = $pdo->query("SELECT COUNT(*) FROM found_items")->fetchColumn();
$totalAnnouncements = $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();

// ----------------- Recent Activity -----------------
$recentStudents = $pdo->query("SELECT name, iub_id, created_at FROM students ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$recentLost = $pdo->query("SELECT item_name, status, created_at FROM lost_items ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$recentFound = $pdo->query("SELECT item_name, status, created_at FROM found_items ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$recentAnnouncements = $pdo->query("SELECT title, created_at FROM announcements ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// ----------------- Admin Activity (Real Data) -----------------
$recentAdminActivity = $pdo->prepare("SELECT action, created_at FROM admin_activity WHERE admin_id = ? ORDER BY created_at DESC LIMIT 5");
$recentAdminActivity->execute([$_SESSION['user_id']]);
$recentAdminActivity = $recentAdminActivity->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Campus Connect</title>
<link rel="stylesheet" href="css/student.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; margin:0; background:#f5f8fa; color:#1e3a5f; display:flex; flex-direction:column; min-height:100vh; }

/* Header */
.header { display:flex; justify-content:space-between; align-items:center; background:#007cc7; color:white; padding:10px 20px; flex-wrap:wrap; }
.header-left { display:flex; align-items:center; gap:15px; }
.header-left .logo { height:50px; }
.header-right { display:flex; align-items:center; gap:15px; }
.header-right .logout-btn { color:white; text-decoration:none; background:#e53935; padding:6px 12px; border-radius:5px; }
.header-right .logout-btn:hover { background:#b71c1c; }

/* Top Nav */
.top-nav { display:flex; gap:20px; background:#e5f4fc; padding:10px 20px; box-shadow:0 2px 4px rgba(0,0,0,0.1); flex-wrap:wrap; }
.top-nav a { text-decoration:none; color:#007cc7; font-weight:600; padding:8px 12px; border-radius:6px; transition:0.3s; }
.top-nav a:hover, .top-nav a.active { background:#007cc7; color:white; }

/* Recent Activity by Admin */
.admin-activity { max-width:1200px; margin:20px auto; padding:0 20px; }
.admin-activity h3 { color:#007cc7; margin-bottom:10px; }
.admin-activity ul { list-style:none; padding:0; margin:0; }
.admin-activity li { background:rgba(255,255,255,0.2); backdrop-filter:blur(10px); margin-bottom:8px; padding:10px 12px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.08); font-size:14px; }

/* Dashboard Cards */
.dashboard-cards {
  display: grid;
  grid-template-columns: repeat(6, 1fr); /* 6 cards in one row */
  gap: 15px;
  max-width: 1200px;
  margin: 20px auto;
  padding: 0 20px;
}

.glass-card { background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius:15px; padding:25px 15px; text-align:center; color:#0f172a; transition: transform 0.3s, box-shadow 0.3s; text-decoration:none; }
.glass-card:hover { transform: scale(1.05); box-shadow:0 10px 20px rgba(0,0,0,0.15); }
.card-icon {
  font-size: 28px; /* smaller than before */
  margin-bottom: 8px;
  color: #007cc7;
}.card-title { font-weight:bold; font-size:1.1em; margin-bottom:5px; }
.card-desc { font-size:0.9em; color:#333; }

/* Recent Lists */
.recent-lists { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px; max-width:1200px; margin:0 auto 40px auto; padding:0 20px; }
.recent-list { background:white; padding:15px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.08); }
.recent-list h4 { margin-bottom:10px; color:#007cc7; }
.recent-list ul { list-style:none; padding:0; margin:0; }
.recent-list li { margin-bottom:6px; font-size:14px; color:#1e3a5f; }

/* Footer */
footer.footer { background:#0f172a; color:#e2e8f0; text-align:center; padding:20px 0; margin-top:auto; user-select:none; }

/* Responsive: wrap cards for tablets and mobile */
@media(max-width:1024px){
  .dashboard-cards {
    grid-template-columns: repeat(3, 1fr); /* 3 per row */
  }
}
@media(max-width:768px){
  .dashboard-cards {
    grid-template-columns: repeat(2, 1fr); /* 2 per row */
  }
}
@media(max-width:480px){
  .dashboard-cards {
    grid-template-columns: 1fr; /* 1 per row */
  }
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
    <span class="user-name"><?php echo htmlspecialchars($admin['full_name']); ?></span>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</header>

<nav class="top-nav">
  <a href="admin-dashboard.php" class="active">Dashboard</a>
  <a href="manage-users.php">Manage Users</a>
  <a href="manage-courses.php">Manage Courses</a>
  <a href="found-items.php">Found Items</a>
  <a href="lost-items.php">Lost Items</a>
  <a href="announcements.php">Announcements</a>
</nav>

<main class="dashboard">



<!-- Dashboard Cards (6 cards, 3 per row) -->
<div class="dashboard-cards">
  <a href="manage-users.php" class="glass-card">
    <div class="card-icon"><i class="fas fa-users"></i></div>
    <div class="card-title">Total Students</div>
    <div class="card-desc"><?php echo $totalStudents; ?> students</div>
  </a>
  <a href="manage-users.php" class="glass-card">
    <div class="card-icon"><i class="fas fa-user-tie"></i></div>
    <div class="card-title">Total Staff</div>
    <div class="card-desc"><?php echo $totalStaff; ?> staff</div>
  </a>
  <a href="manage-courses.php" class="glass-card">
    <div class="card-icon"><i class="fas fa-book"></i></div>
    <div class="card-title">Total Courses</div>
    <div class="card-desc"><?php echo $totalCourses; ?></div>
  </a>
  <a href="lost-items.php" class="glass-card">
    <div class="card-icon"><i class="fas fa-search"></i></div>
    <div class="card-title">Lost Items</div>
    <div class="card-desc"><?php echo $totalLost; ?></div>
  </a>
  <a href="found-items.php" class="glass-card">
    <div class="card-icon"><i class="fas fa-gift"></i></div>
    <div class="card-title">Found Items</div>
    <div class="card-desc"><?php echo $totalFound; ?></div>
  </a>
  <a href="announcements.php" class="glass-card">
    <div class="card-icon"><i class="fas fa-bullhorn"></i></div>
    <div class="card-title">Announcements</div>
    <div class="card-desc"><?php echo $totalAnnouncements; ?></div>
  </a>
</div>

<!-- Recent Students / Lost / Found / Announcements -->
<section class="recent-lists">
  <div class="recent-list">
    <h4>Recent Students</h4>
    <ul>
      <?php foreach($recentStudents as $s): ?>
        <li><?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['iub_id']); ?>)</li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="recent-list">
    <h4>Recent Lost Items</h4>
    <ul>
      <?php foreach($recentLost as $l): ?>
        <li><?php echo htmlspecialchars($l['item_name']); ?> - <?php echo htmlspecialchars($l['status']); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="recent-list">
    <h4>Recent Found Items</h4>
    <ul>
      <?php foreach($recentFound as $f): ?>
        <li><?php echo htmlspecialchars($f['item_name']); ?> - <?php echo htmlspecialchars($f['status']); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="recent-list">
    <h4>Recent Announcements</h4>
    <ul>
      <?php foreach($recentAnnouncements as $a): ?>
        <li><?php echo htmlspecialchars($a['title']); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
