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
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Staff Dashboard - Campus Connect</title>
<link rel="stylesheet" href="css/student.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
/* Header */
header.header { display:flex; justify-content:space-between; align-items:center; padding:10px 20px; background:#e5f4fc; flex-wrap:wrap; }
header .title-text h1 { margin:0; color:#007cc7; }
header .title-text p { margin:0; font-size:0.9em; color:#007cc7; }
header .header-right { display:flex; align-items:center; gap:15px; }

/* Navbar */
nav.top-nav { display:flex; background:#e5f4fc; padding:10px 20px; flex-wrap:wrap; gap:10px; }
nav.top-nav a { text-decoration:none; padding:8px 12px; color:#007cc7; font-weight:bold; border-radius:5px; transition:0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background:#007cc7; color:#fff; }

/* Dashboard / Main */
main.dashboard { flex:1; max-width:1200px; margin:30px auto 60px auto; padding:0 20px; display:flex; flex-direction:column; gap:20px; color:#1e3a5f; }
.activity-gist h2 { color:#007cc7; font-weight:700; font-size:22px; margin-bottom:15px; }

/* Activity Cards */
.activity-cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:20px; }
.activity-card { background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius:15px; padding:25px; box-shadow:0 8px 20px rgba(0,124,199,0.1); transition: transform 0.3s; display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center; }
.activity-card:hover { transform: translateY(-5px); box-shadow:0 12px 24px rgba(0,124,199,0.2); }
.activity-card h3 { margin-bottom:10px; color:#005b9f; font-size:1.1em; }
.activity-card p { font-size:0.95em; }

/* Footer */
footer.footer { background:#0f172a; color:#e2e8f0; text-align:center; padding:20px 0; user-select:none; margin-top:auto; }

/* Responsive */
@media(max-width:768px){
    header.header { flex-direction:column; align-items:flex-start; gap:15px; }
    .logo { width:80px; height:50px; }
    .title-text h1 { font-size:22px; }
    nav.top-nav { justify-content:flex-start; overflow-x:auto; padding:10px; gap:8px; }
    nav.top-nav a { padding:6px 12px; font-size:14px; }
    main.dashboard { margin:20px 15px 40px 15px; padding:0 10px; }
}
</style>
</head>
<body>

<header class="header">
  <div class="header-left" style="display:flex; align-items:center; gap:10px;">
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
  <a href="staff-dashboard.php" class="active">Home</a>
  <a href="#">Profile</a>
  <a href="#">Lost &amp; Found</a>
  <a href="#">CCTV Reporting</a>
  <a href="#">Event Booking</a>
  <a href="#">Notifications</a>
</nav>

<main class="dashboard">
  <section class="activity-gist">
    <h2><?php echo htmlspecialchars($firstName); ?>'s Recent Activity</h2>
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
