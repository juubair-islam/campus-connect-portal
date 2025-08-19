<?php
session_start();

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
$stmt = $pdo->prepare("SELECT uid, name FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$firstName = explode(' ', trim($student['name']))[0];

// Fetch all enrolled courses
$stmt = $pdo->prepare("
    SELECT c.course_id, c.course_code, c.course_name, c.available_days, c.start_time, c.end_time, s.name AS tutor_name
    FROM course_enrollments e
    JOIN courses c ON e.course_id = c.course_id
    JOIN students s ON c.tutor_uid = s.uid
    WHERE e.learner_uid = :learner_uid
");
$stmt->execute(['learner_uid' => $student['uid']]);
$allCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Today's courses
$today = date('D'); // Mon, Tue, etc.
$todayCourses = [];
foreach ($allCourses as $course) {
    $days = explode(',', $course['available_days']);
    if (in_array($today, $days)) {
        $todayCourses[] = $course;
    }
}

// Count new materials per course
$courseIds = array_column($allCourses, 'course_id');
$newMaterialsCount = 0;
if (!empty($courseIds)) {
    $inQuery = implode(',', array_fill(0, count($courseIds), '?'));
    $stmtMat = $pdo->prepare("
        SELECT COUNT(*) as cnt
        FROM course_materials
        WHERE course_id IN ($inQuery)
          AND upload_date >= CURDATE() - INTERVAL 7 DAY
    ");
    $stmtMat->execute($courseIds);
    $newMaterialsCount = $stmtMat->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Learner Panel - Campus Connect</title>
<link rel="stylesheet" href="../css/student.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
nav.top-nav { display: flex; background: #e5f4fc; padding: 10px 20px; flex-wrap: wrap; }
nav.top-nav a { margin-right: 15px; text-decoration: none; padding: 8px 12px; color: #007cc7; font-weight: bold; border-radius: 5px; transition: 0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background: #007cc7; color: #fff; }

.dashboard { max-width: 1200px; margin: 20px auto; padding: 0 20px; display: flex; flex-direction: column; gap: 20px; }

.today-courses {
    background: #e5f4fc;
    padding: 15px 20px;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0,124,199,0.1);
    font-size: 1em;
    color: #007cc7;
}
.today-courses strong { display: block; margin-bottom: 8px; }

.glass-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
.glass-card { background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); border-radius: 15px; padding: 30px 20px; text-align: center; text-decoration: none; color: #0f172a; transition: transform 0.3s ease, box-shadow 0.3s ease; position: relative; }
.glass-card:hover { transform: scale(1.05); box-shadow: 0 10px 20px rgba(0,0,0,0.15); }

.card-icon { font-size: 40px; margin-bottom: 15px; color: #007cc7; }
.card-title { font-weight: bold; font-size: 1.2em; margin-bottom: 5px; }
.card-desc { font-size: 0.9em; color: #333; }

.notification-badge {
    position: absolute;
    top: 10px;
    right: 15px;
    background: #dc3545;
    color: #fff;
    font-size: 0.8em;
    font-weight: bold;
    padding: 4px 8px;
    border-radius: 12px;
}

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
    <a href="../tutor/tutor-dashboard.php">Tutor Panel</a>
    <a href="../learner/learner-dashboard.php"class="active">Learner Panel</a>
</nav>


<main class="dashboard">
    <div class="today-courses">
        <strong>Today's Tuitions:</strong>
        <?php if (!empty($todayCourses)): ?>
            <?php foreach ($todayCourses as $course): ?>
                <?php echo htmlspecialchars($course['course_name'] . " (" . date('h:i A', strtotime($course['start_time'])) . " - " . date('h:i A', strtotime($course['end_time'])) . ")"); ?><br>
            <?php endforeach; ?>
        <?php else: ?>
            No courses scheduled for today.
        <?php endif; ?>
    </div>

    <div class="glass-grid">
        <a href="learner-courses-list.php" class="glass-card">
            <div class="card-icon"><i class="fas fa-search"></i></div>
            <div class="card-title">Find Courses</div>
            <div class="card-desc">Browse all available courses and enroll</div>
        </a>

        <a href="learner-enrolled-courses.php" class="glass-card">
            <?php if ($newMaterialsCount > 0): ?>
                <span class="notification-badge"><?php echo $newMaterialsCount; ?> New</span>
            <?php endif; ?>
            <div class="card-icon"><i class="fas fa-book-open"></i></div>
            <div class="card-title">My Enrolled Courses</div>
            <div class="card-desc">Check your current courses and progress</div>
        </a>
    </div>
</main>

<footer class="footer">
    &copy; 2025 Campus Connect | Independent University, Bangladesh
</footer>

</body>
</html>
