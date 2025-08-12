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
    die("DB connection failed: " . $e->getMessage());
}

// Get current learner's UID and name
$stmt = $pdo->prepare("SELECT uid, name FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Fetch enrolled courses details for this learner
// Join course_enrollments, courses, students (tutor)
$sql = "
SELECT c.course_id, c.course_name, c.course_code, c.available_days, c.start_time, c.end_time, c.description,
       s.name AS tutor_name
FROM course_enrollments ce
JOIN courses c ON ce.course_id = c.course_id
JOIN students s ON c.tutor_uid = s.uid
WHERE ce.learner_uid = :learner_uid
ORDER BY ce.enrollment_date DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['learner_uid' => $currentUser['uid']]);
$enrolledCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For each course, fetch materials
$materialsByCourse = [];
if ($enrolledCourses) {
    $courseIds = array_column($enrolledCourses, 'course_id');
    $inQuery = implode(',', array_fill(0, count($courseIds), '?'));
    $stmtMat = $pdo->prepare("SELECT course_id, title, description, file_url, upload_date FROM course_materials WHERE course_id IN ($inQuery) ORDER BY upload_date DESC");
    $stmtMat->execute($courseIds);
    $materials = $stmtMat->fetchAll(PDO::FETCH_ASSOC);
    foreach ($materials as $mat) {
        $materialsByCourse[$mat['course_id']][] = $mat;
    }
}

function formatTimeRange($start, $end) {
    return date('h:i A', strtotime($start)) . " - " . date('h:i A', strtotime($end));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>My Enrolled Courses - Campus Connect</title>
<link rel="stylesheet" href="../css/student.css" />
<style>
  main {
    max-width: 900px;
    margin: 1em auto;
    background: #e5f4fc;
    padding: 1.5em;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,124,199,0.15);
  }
  h2.course-title {
    margin-top: 2em;
    color: #007cc7;
    border-bottom: 2px solid #007cc7;
    padding-bottom: 0.2em;
  }
  .course-details p {
    margin: 0.3em 0;
  }
  .materials-list {
    margin-top: 1em;
    padding-left: 1em;
  }
  .material-item {
    margin-bottom: 0.7em;
  }
  .material-title {
    font-weight: 600;
    color: #007cc7;
  }
  .material-description {
    font-style: italic;
    color: #555;
  }
  .material-link {
    color: #005fa3;
    text-decoration: none;
  }
  .material-link:hover {
    text-decoration: underline;
  }
  .no-courses {
    font-style: italic;
    color: #555;
    margin-top: 1em;
  }
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
    <span class="user-name"><?php echo htmlspecialchars($currentUser['name']); ?></span>
    <a href="../logout.php" class="logout-btn">Logout</a>
  </div>
</header>

<nav class="top-nav">
  <a href="../StudentProfile.php">👤 Profile</a>
  <a href="../lost-found.php">🏷️ Lost &amp; Found</a>
  <a href="../cctv-reporting.php">📹 CCTV Reporting</a>
  <a href="../event-booking.php">📅 Event Booking</a>
  <a href="learner-dashboard.php">🎓 Learner Panel</a>
  <a href="learner-courses-list.php">📚 Available Courses</a>
  <a href="learner-course-requests.php">📨 Course Requests</a>
  <a href="learner-course-materials.php">📁 Course Materials</a>
</nav>

<main>
  <h2>My Enrolled Courses</h2>

  <?php if (empty($enrolledCourses)): ?>
    <p class="no-courses">You are not enrolled in any courses yet.</p>
  <?php else: ?>
    <?php foreach ($enrolledCourses as $course): ?>
      <section>
        <h2 class="course-title"><?php echo htmlspecialchars($course['course_code'] . " - " . $course['course_name']); ?></h2>
        <div class="course-details">
          <p><strong>Available Days:</strong> <?php echo htmlspecialchars(str_replace(',', ', ', $course['available_days'] ?? 'N/A')); ?></p>
          <p><strong>Time:</strong> <?php echo formatTimeRange($course['start_time'] ?? '00:00', $course['end_time'] ?? '00:00'); ?></p>
          <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
          <p><strong>Tutor:</strong> <?php echo htmlspecialchars($course['tutor_name']); ?></p>
        </div>
        <div class="course-materials">
          <h3>Materials:</h3>
          <?php if (!empty($materialsByCourse[$course['course_id']])): ?>
            <ul class="materials-list">
              <?php foreach ($materialsByCourse[$course['course_id']] as $material): ?>
                <li class="material-item">
                  <span class="material-title"><?php echo htmlspecialchars($material['title']); ?></span><br />
                  <?php if ($material['description']): ?>
                    <span class="material-description"><?php echo htmlspecialchars($material['description']); ?></span><br />
                  <?php endif; ?>
                  <?php if ($material['file_url']): ?>
                    <a href="<?php echo htmlspecialchars($material['file_url']); ?>" target="_blank" class="material-link">Download/View</a>
                  <?php endif; ?>
                  <small> (Uploaded: <?php echo date("M d, Y", strtotime($material['upload_date'])); ?>)</small>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p><em>No materials uploaded yet.</em></p>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
