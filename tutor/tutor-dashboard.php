<?php
session_start();

// Student login check (tutor role assumed to be 'student' for simplicity)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
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

// Fetch student info
$stmt = $pdo->prepare("SELECT name, uid FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch all tutor courses
$stmt = $pdo->prepare("
    SELECT course_id, course_name, available_days, start_time, end_time
    FROM courses
    WHERE tutor_uid = ?
    ORDER BY created_at DESC
");
$stmt->execute([$student['uid']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pending requests per course
$pendingRequests = [];
foreach ($courses as $course) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_requests WHERE course_id = ? AND status = 'pending'");
    $stmt->execute([$course['course_id']]);
    $pendingRequests[$course['course_id']] = $stmt->fetchColumn();
}

// Get today's weekday name (e.g., Mon, Tue)
$todayWeekday = date('D');

// Filter courses scheduled today
$todaysCourses = array_filter($courses, function($course) use ($todayWeekday) {
    $days = explode(',', $course['available_days']);
    return in_array($todayWeekday, $days);
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tutor Panel - Campus Connect</title>
<link rel="stylesheet" href="../css/studprofile.css" />
<link rel="stylesheet" href="../css/student.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
body { display: flex; flex-direction: column; min-height: 100vh; margin: 0; background: #f5f8fa; }
nav.top-nav { display: flex; background: #e5f4fc; padding: 10px 20px; flex-wrap: wrap; }
nav.top-nav a { margin-right: 15px; text-decoration: none; padding: 8px 12px; color: #007cc7; font-weight: bold; border-radius: 5px; transition: 0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background: #007cc7; color: #fff; }
.notification-bar { background: #d0ebff; color: #007cc7; padding: 15px 20px; font-weight: bold; border-radius: 8px; margin: 20px auto 0 auto; max-width: 1200px; text-align: left; }
.notification-bar ul { margin: 10px 0 0 20px; padding: 0; }
.notification-bar li { margin-bottom: 5px; }
.glass-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; max-width: 1200px; margin: 30px auto; padding: 0 20px; }
.glass-card { background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 15px; padding: 30px 20px; text-align: center; text-decoration: none; color: #0f172a; transition: transform 0.3s ease, box-shadow 0.3s ease; }
.glass-card:hover { transform: scale(1.05); box-shadow: 0 10px 20px rgba(0,0,0,0.15); }
.card-icon { font-size: 40px; margin-bottom: 15px; color: #007cc7; }
.card-title { font-weight: bold; font-size: 1.2em; margin-bottom: 5px; }
.card-desc { font-size: 0.9em; color: #333; }
.notification-badge { background: #e74c3c; color: #fff; padding: 2px 8px; border-radius: 50%; font-size: 0.85em; margin-left: 5px; }
footer.footer { background: #0f172a; color: #e2e8f0; text-align: center; padding: 20px 0; user-select: none; margin-top: auto; }
</style>
</head>
<body>

<header class="header">
    <div class="header-left">
        <img src="../images/logo.png" alt="Campus Connect Logo" class="logo" />
        <div class="title-text">
            <h1>Campus Connect</h1>
            <p class="tagline">Bridge to Your IUB Community</p>
        </div>
    </div>
    <div class="header-right">
        <span class="user-name"><?php echo htmlspecialchars($student['name']); ?></span>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<nav class="top-nav">
    <a href="../student-dashboard.php">Home</a>
    <a href="../StudentProfile.php">Profile</a>
    <a href="../lost & found/lost-found.php">Lost &amp; Found</a>
    <a href="tutor-dashboard.php" class="active">Tutor Panel</a>
    <a href="../learner/learner-dashboard.php">Learner Panel</a>
</nav>

<main class="dashboard">
    <div class="notification-bar">
        <strong>Today's Tuitions:</strong>
        <?php if(count($todaysCourses) > 0): ?>
            <ul>
                <?php foreach($todaysCourses as $tc): ?>
                    <li><?php echo htmlspecialchars($tc['course_name']); ?> (<?php echo $tc['start_time'] . ' - ' . $tc['end_time']; ?>)</li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No courses scheduled for today.</p>
        <?php endif; ?>
    </div>

    <div class="glass-grid">
        <a href="tutor-courses-create.php" class="glass-card">
            <div class="card-icon"><i class="fas fa-plus-circle"></i></div>
            <div class="card-title">Post New Tuition</div>
            <div class="card-desc">Create a new tuition/course offering</div>
        </a>

        <a href="tutor-course-requests.php" class="glass-card">
            <div class="card-icon"><i class="fas fa-envelope"></i></div>
            <div class="card-title">Tuition Requests
                <?php $pendingTotal = array_sum($pendingRequests);
                if($pendingTotal > 0) echo "<span class='notification-badge'>".$pendingTotal."</span>"; ?>
            </div>
            <div class="card-desc">View requests from learners</div>
        </a>

        <a href="tutor-courses-list.php" class="glass-card">
            <div class="card-icon"><i class="fas fa-book"></i></div>
            <div class="card-title">My Courses</div>
            <div class="card-desc">Manage your current courses</div>
        </a>
    </div>
</main>

<footer class="footer">
    &copy; 2025 Campus Connect | Independent University, Bangladesh
</footer>

</body>
</html>
