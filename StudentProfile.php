<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
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

$stmt = $pdo->prepare("SELECT iub_id, name, department, major, minor, email, contact_number, role, created_at
                       FROM students
                       WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Profile - Campus Connect</title>
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
    <span class="user-name"><?php echo htmlspecialchars($student['name']); ?></span>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</header>

<!-- Top Navigation Bar -->
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



<main class="dashboard">
  <section class="profile-section">
    <h2>📋 Student Profile</h2>
    <div class="profile-card">
      <p><strong>Name:</strong> <?php echo htmlspecialchars($student['name']); ?></p>
      <p><strong>IUB ID:</strong> <?php echo htmlspecialchars($student['iub_id']); ?></p>
      <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department']); ?></p>
      <p><strong>Major:</strong> <?php echo htmlspecialchars($student['major']); ?></p>
      <p><strong>Minor:</strong> <?php echo htmlspecialchars($student['minor']); ?></p>
      <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
      <p><strong>Contact:</strong> <?php echo htmlspecialchars($student['contact_number']); ?></p>
      <p><strong>Role:</strong> <?php echo htmlspecialchars($student['role']); ?></p>
      <p><strong>Joined On:</strong> <?php echo date("F j, Y", strtotime($student['created_at'])); ?></p>
    </div>
  </section>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
