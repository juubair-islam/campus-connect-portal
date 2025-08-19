<?php
session_start();

// If user not logged in or role is not student → redirect to login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
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
$stmt = $pdo->prepare("SELECT iub_id, uid, name, department, major, minor, email, contact_number, role, created_at
                       FROM students
                       WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$firstName = explode(' ', trim($student['name']))[0];
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Student Dashboard - Campus Connect</title>
<link rel="stylesheet" href="css/student.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
nav.top-nav { display: flex; background: #e5f4fc; padding: 10px 20px; flex-wrap: wrap; }
nav.top-nav a { margin-right: 15px; text-decoration: none; padding: 8px 12px; color: #007cc7; font-weight: bold; border-radius: 5px; transition: 0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background: #007cc7; color: #fff; }

/* Dropdown */
.dropdown { position: relative; }
.dropdown-content {
    display:none; position:absolute; background: rgba(229,244,252,0.95); top:100%; left:0;
    min-width:160px; box-shadow:0 4px 8px rgba(0,124,199,0.2); border-radius:8px; z-index:100;
}
.dropdown-content a {
    display:block; padding:10px 15px; color:#007cc7; font-weight:500; text-decoration:none; border-radius:6px;
}
.dropdown-content a:hover { background:#007cc7; color:white; }
.dropdown:hover .dropdown-content { display:block; }

/* Dashboard / Main */
main.dashboard { flex:1; max-width:1200px; margin:30px auto 60px auto; padding:0 20px; display:flex; flex-direction:column; gap:20px; color:#1e3a5f; }
.activity-gist h2 { color:#007cc7; font-weight:700; font-size:22px; margin-bottom:15px; }
.activity-cards { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:20px; }
.activity-card { background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius:15px; padding:25px; text-align:center; box-shadow:0 8px 20px rgba(0,124,199,0.1); transition: transform 0.3s; }
.activity-card:hover { transform: translateY(-5px); box-shadow:0 12px 24px rgba(0,124,199,0.2); }
.activity-card h3 { margin-bottom:10px; color:#005b9f; }

/* Footer */
footer.footer { background: #0f172a; color:#e2e8f0; text-align:center; padding:20px 0; user-select:none; margin-top:auto; }

/* Responsive */
@media(max-width:768px){
    header.header { flex-direction:column; align-items:flex-start; gap:15px; }
    .header-left { gap:10px; }
    .logo { width:80px; height:50px; }
    .title-text h1 { font-size:22px; }
    .header-right { width:100%; justify-content:space-between; }
    nav.top-nav { justify-content:flex-start; overflow-x:auto; padding:10px; gap:8px; }
    nav.top-nav a, nav.top-nav .dropbtn { padding:6px 12px; font-size:14px; }
    main.dashboard { margin:20px 15px 40px 15px; padding:0 10px; }
}
</style>
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
    <span class="user-name"><?php echo htmlspecialchars($student['name']); ?></span>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</header>

<nav class="top-nav">
  <a href="student-dashboard.php" class="<?php echo $currentPage=='student-dashboard.php' ? 'active' : ''; ?>">Home</a>
  <a href="StudentProfile.php" class="<?php echo $currentPage=='StudentProfile.php' ? 'active' : ''; ?>">Profile</a>
  <a href="lost & found/lost-found.php" class="<?php echo $currentPage=='lost-found.php' ? 'active' : ''; ?>">Lost &amp; Found</a>
  <a href="tutor/tutor-dashboard.php" class="<?php echo $currentPage=='tutor-dashboard.php' ? 'active' : ''; ?>">Tutor Panel</a>
  <a href="learner/learner-dashboard.php">Learner Panel</a>
</nav>

<main class="dashboard">
  <section class="activity-gist">
    <h2><?php echo htmlspecialchars($firstName); ?>'s Recent Activity</h2>
    <div class="activity-cards">
      <div class="activity-card">
        <h3>Lost & Found Reports</h3>
        <p>You have reported <strong>3</strong> items recently.</p>
      </div>
      <div class="activity-card">
        <h3>CCTV Reports</h3>
        <p><strong>2</strong> reports are under review.</p>
      </div>
      <div class="activity-card">
        <h3>Event Bookings</h3>
        <p>You have <strong>5</strong> upcoming events.</p>
      </div>
      <div class="activity-card">
        <h3>Tutor/Learner Sessions</h3>
        <p><strong>4</strong> sessions scheduled this month.</p>
      </div>
    </div>
  </section>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
