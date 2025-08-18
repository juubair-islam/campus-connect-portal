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
$stmt = $pdo->prepare("SELECT iub_id, name, department, major, minor, email, contact_number, role, created_at
                       FROM students
                       WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// If no student found (shouldn’t happen normally)
if (!$student) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Extract first name for greeting
$firstName = explode(' ', trim($student['name']))[0];

// Get current page filename for active link
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Dashboard - Campus Connect</title>
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

<!-- Top Navbar -->
<nav class="top-nav">
  <a href="student-dashboard.php" class="<?php echo $currentPage=='student-dashboard.php' ? 'active' : ''; ?>">Home</a>
  <a href="StudentProfile.php" class="<?php echo $currentPage=='StudentProfile.php' ? 'active' : ''; ?>">Profile</a>
  <a href="lost-found.php" class="<?php echo $currentPage=='lost-found.php' ? 'active' : ''; ?>">Lost &amp; Found</a>

  <!-- Tutor Dropdown -->
  <div class="dropdown">
    <span class="dropbtn <?php echo in_array($currentPage, ['tutor-courses-list.php','tutor-course-requests.php']) ? 'active' : ''; ?>">Tutor ▾</span>
    <div class="dropdown-content">
      <a href="tutor/tutor-courses-list.php">My Courses</a>
      <a href="tutor/tutor-course-requests.php">Course Requests</a>
    </div>
  </div>

  <!-- Learner Dropdown -->
  <div class="dropdown">
    <span class="dropbtn <?php echo in_array($currentPage, ['learner-courses-list.php','learner-enrolled-courses.php']) ? 'active' : ''; ?>">Learner ▾</span>
    <div class="dropdown-content">
      <a href="learner/learner-courses-list.php">Find Course</a>
      <a href="learner/learner-enrolled-courses.php">Enrolled Courses</a>
    </div>
  </div>
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
