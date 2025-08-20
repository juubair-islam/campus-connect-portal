<?php
session_start();

// Check student login
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
    die("DB connection failed: " . $e->getMessage());
}

// Student info
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$firstName = explode(" ", trim($student['name']))[0];
$uid = $student['uid'];

// Stats
$lostCount = $pdo->prepare("SELECT COUNT(*) FROM lost_items WHERE reporter_uid = ?");
$lostCount->execute([$uid]);
$lostCount = $lostCount->fetchColumn();

$foundCount = $pdo->prepare("SELECT COUNT(*) 
                             FROM lost_items 
                             WHERE reporter_uid = ? AND found_id IS NOT NULL");
$foundCount->execute([$uid]);
$foundCount = $foundCount->fetchColumn();

$tutorCourses = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE tutor_uid = ?");
$tutorCourses->execute([$uid]);
$tutorCourses = $tutorCourses->fetchColumn();

$learnerRequests = $pdo->prepare("SELECT COUNT(*) 
                                  FROM course_requests 
                                  WHERE learner_uid = ? AND status != 'rejected'");
$learnerRequests->execute([$uid]);
$learnerRequests = $learnerRequests->fetchColumn();

// Recent activities
$recentLost = $pdo->prepare("SELECT item_name, created_at 
                             FROM lost_items 
                             WHERE reporter_uid = ? 
                             ORDER BY created_at DESC LIMIT 3");
$recentLost->execute([$uid]);
$recentLost = $recentLost->fetchAll(PDO::FETCH_ASSOC);

$recentRequests = $pdo->prepare("SELECT cr.status, c.course_code, cr.request_date 
                                 FROM course_requests cr
                                 JOIN courses c ON cr.course_id = c.course_id
                                 WHERE cr.learner_uid = ?
                                 ORDER BY cr.request_date DESC LIMIT 3");
$recentRequests->execute([$uid]);
$recentRequests = $recentRequests->fetchAll(PDO::FETCH_ASSOC);




$totalEnrolledStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ce.learner_uid) 
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.course_id
    WHERE c.tutor_uid = ?
");
$totalEnrolledStmt->execute([$uid]);
$totalEnrolled = $totalEnrolledStmt->fetchColumn();






$totalLearnersStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM course_requests cr
    JOIN courses c ON cr.course_id = c.course_id
    WHERE c.tutor_uid = ?
");
$totalLearnersStmt->execute([$uid]);
$totalLearners = $totalLearnersStmt->fetchColumn();


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
<title>Student Dashboard - Campus Connect</title>
<link rel="stylesheet" href="css/student.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
main.dashboard { max-width:1200px; margin:30px auto 60px auto; padding:0 20px; display:flex; flex-direction:column; gap:30px; color:#1e3a5f; }
/* Welcome */
.welcome-box { background:#007cc7; color:white; padding:25px 30px; border-radius:15px; text-align:center; }
.welcome-box h2 { font-size:26px; margin-bottom:8px; }
/* Profile snapshot */
.profile-snapshot { background:#e5f4fc; padding:20px; border-radius:15px; box-shadow:0 4px 12px rgba(0,124,199,0.1); }
.profile-snapshot h3 { margin:0 0 10px; color:#007cc7; }
.profile-snapshot p { margin:4px 0; }
/* Stats */
.stats-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:20px; }
.stat-card { background:white; border-radius:15px; padding:25px; text-align:center; box-shadow:0 6px 14px rgba(0,124,199,0.12); transition:0.3s; }
.stat-card:hover { transform:translateY(-5px); }
.stat-card h3 { margin-bottom:8px; color:#005b9f; }
.stat-card i { font-size:28px; margin-bottom:5px; color:#007cc7; }
/* Quick links */
.quick-links { display:grid; grid-template-columns: repeat(auto-fit,minmax(150px,1fr)); gap:15px; }
.quick-link { background:#007cc7; color:white; padding:18px; border-radius:12px; text-align:center; text-decoration:none; font-weight:600; transition:0.3s; }
.quick-link:hover { background:#005b9f; }
/* Announcements & Activity */
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.card { background:white; padding:20px; border-radius:15px; box-shadow:0 6px 14px rgba(0,0,0,0.08); }
.card h3 { color:#007cc7; margin-bottom:12px; }
.card ul { list-style:none; margin:0; padding:0; }
.card li { margin-bottom:15px; padding-bottom:10px; border-bottom:1px solid #eee; }
.card li:last-child { border-bottom:none; }
.card li small { color:#555; display:block; margin-bottom:4px; }
nav.top-nav { display: flex; background: #e5f4fc; padding: 10px 20px; flex-wrap: wrap; }
nav.top-nav a { margin-right: 15px; text-decoration: none; padding: 8px 12px; color: #007cc7; font-weight: bold; border-radius: 5px; transition: 0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background: #007cc7; color: #fff; }
.announcements-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.announcements-list li {
    position: relative;
    padding: 15px 20px 15px 35px;
    margin-bottom: 15px;
    border-left: 4px solid #007cc7; /* blue left border to point each announcement */
    background: #f9f9f9;
    border-radius: 8px;
}

.announcements-list li:last-child {
    margin-bottom: 0;
}

.announcements-list li small {
    display: block;
    margin-bottom: 5px;
    color: #555;
}

.announcements-list li p {
    margin: 0;
}


/* Responsive */
@media(max-width:768px){
  .grid-2 { grid-template-columns:1fr; }
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
    <a href="student-dashboard.php" class="active">Home</a>
    <a href="StudentProfile.php">Profile</a>
    <a href="lost & found/lost-found.php">Lost &amp; Found</a>
    <a href="tutor/tutor-dashboard.php">Tutor Panel</a>
    <a href="learner/learner-dashboard.php">Learner Panel</a>
</nav>

<main class="dashboard">

  <!-- Profile Snapshot -->
  <div class="profile-snapshot">
    <h3>Welcome back, <?php echo htmlspecialchars($firstName); ?></h3>
    <p><strong>IUB ID:</strong> <?php echo htmlspecialchars($student['iub_id']); ?></p>
    <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department']); ?></p>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card"><i class="fas fa-search"></i><h3>Lost Items</h3><p><strong><?php echo $lostCount; ?></strong> reported</p></div>
    <div class="stat-card"><i class="fas fa-box-open"></i><h3>Found Items</h3><p><strong><?php echo $foundCount; ?></strong> matched</p></div>
    <div class="stat-card"><i class="fas fa-chalkboard-teacher"></i><h3>Your Tutor Courses</h3><p><strong><?php echo $tutorCourses; ?></strong> courses</p></div>
    <div class="stat-card"><i class="fas fa-book-reader"></i><h3>My Enrolled Course</h3><p><strong><?php echo $learnerRequests; ?></strong> active</p></div>
    <div class="stat-card"><i class="fas fa-users"></i><h3>Total Learners</h3><p><strong><?php echo $totalEnrolled; ?></strong> enrolled</p></div>


    
  </div>

  <!-- Announcements & Recent Activity -->
  <div class="grid-2">
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

    <div class="card">
      <h3>Recent Activity</h3>
      <ul>
        <?php foreach ($recentLost as $row): ?>
          <li>🔍 Lost item reported: <?php echo htmlspecialchars($row['item_name']); ?> (<?php echo date("M d", strtotime($row['created_at'])); ?>)</li>
        <?php endforeach; ?>
        <?php foreach ($recentRequests as $row): ?>
          <li>📚 Course <?php echo htmlspecialchars($row['course_code']); ?> request (<?php echo htmlspecialchars($row['status']); ?>)</li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
