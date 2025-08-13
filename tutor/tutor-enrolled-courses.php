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

// Fetch tutor info
$stmt = $pdo->prepare("SELECT uid, name FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tutor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tutor) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$firstName = explode(' ', trim($tutor['name']))[0];

// Fetch courses of this tutor
$coursesStmt = $pdo->prepare("SELECT course_id, course_code, course_name FROM courses WHERE tutor_uid = ? ORDER BY created_at DESC");
$coursesStmt->execute([$tutor['uid']]);
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

// For each course, fetch enrolled learners
$enrollments = [];

foreach ($courses as $course) {
    $enrollStmt = $pdo->prepare("
        SELECT s.name AS learner_name, ce.enrollment_date
        FROM course_enrollments ce
        JOIN students s ON ce.learner_uid = s.uid
        WHERE ce.course_id = ?
        ORDER BY ce.enrollment_date DESC
    ");
    $enrollStmt->execute([$course['course_id']]);
    $enrolledLearners = $enrollStmt->fetchAll(PDO::FETCH_ASSOC);
    $enrollments[$course['course_id']] = $enrolledLearners;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Enrolled Learners - Campus Connect</title>
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
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  h2.course-title a.manage-materials-btn {
    font-size: 0.9em;
    padding: 0.3em 0.6em;
    background-color: #007cc7;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    transition: background-color 0.3s ease;
  }
  h2.course-title a.manage-materials-btn:hover {
    background-color: #005fa3;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 0.7em;
  }
  th, td {
    padding: 0.6em;
    border: 1px solid #007cc7;
    text-align: left;
  }
  th {
    background-color: #007cc7;
    color: white;
  }
  .no-learners {
    font-style: italic;
    color: #555;
    margin-top: 0.5em;
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
    <span class="user-name"><?php echo htmlspecialchars($tutor['name']); ?></span>
    <a href="../logout.php" class="logout-btn">Logout</a>
  </div>
</header>
<nav class="top-nav">
  <a href="StudentProfile.php" class="active">Profile</a>
  <a href="lost-found.php">Lost &amp; Found</a>
  <a href="cctv-reporting.php">CCTV Reporting</a>
  <a href="event-booking.php">Event Booking</a>

  <!-- Tutor Menu -->
  <div class="dropdown">
    <span class="dropbtn">Tutor ▾</span>
    <div class="dropdown-content">
      <a href="tutor/tutor-courses-list.php">My Courses</a>
      <a href="tutor/tutor-course-requests.php">Course Requests</a>
    </div>
  </div>

  <!-- Learner Dropdown -->
  <div class="dropdown">
    <a href="#" class="dropbtn">Learner▾</a>
    <div class="dropdown-content">
      <a href="learner/learner-courses-list.php">Find Course</a>
      <a href="learner/learner-enrolled-courses.php">Enrolled Courses</a>
    </div>
  </div>
  </div>
</nav>

<main>
  <h2>Your Enrolled Learners</h2>

  <?php if (empty($courses)): ?>
    <p>You have not created any courses yet.</p>
  <?php else: ?>
    <?php foreach ($courses as $course): ?>
      <h2 class="course-title">
        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
        <a href="tutor-course-materials.php?course_id=<?php echo $course['course_id']; ?>" class="manage-materials-btn" title="Manage Materials">📁 Manage Materials</a>
      </h2>
      <?php if (empty($enrollments[$course['course_id']])): ?>
        <p class="no-learners">No learners enrolled in this course yet.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Learner Name</th>
              <th>Enrollment Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($enrollments[$course['course_id']] as $learner): ?>
              <tr>
                <td><?php echo htmlspecialchars($learner['learner_name']); ?></td>
                <td><?php echo date("M d, Y H:i", strtotime($learner['enrollment_date'])); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>

</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
