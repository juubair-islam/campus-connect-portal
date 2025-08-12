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

// Fetch current user info
$stmt = $pdo->prepare("SELECT uid, name FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

?>

<header class="header">
  <div class="header-left">
    <a href="../index.php" class="logo-link" aria-label="Campus Connect Home">
      <img src="../images/logo.png" alt="Campus Connect Logo" class="logo" />
      <div class="title-text">
        <h1>Campus Connect</h1>
        <p class="tagline">Bridge to Your IUB Community</p>
      </div>
    </a>
  </div>
  <div class="header-right">
    <span class="user-name">Hello, <?php echo htmlspecialchars($currentUser['name']); ?></span>
    <a href="../logout.php" class="logout-btn" aria-label="Logout">Logout</a>
  </div>
</header>

<nav class="top-nav" role="navigation" aria-label="Main Navigation">
  <a href="../StudentProfile.php">👤 Profile</a>
  <a href="../lost-found.php">🏷️ Lost &amp; Found</a>
  <a href="../cctv-reporting.php">📹 CCTV Reporting</a>
  <a href="../event-booking.php">📅 Event Booking</a>

  <a href="learner-dashboard.php">🎓 Learner Panel</a>
  <a href="learner-courses-list.php">📚 Available Courses</a>
  <a href="learner-enrolled-courses.php">📝 Enrolled Courses</a>
  <a href="learner-course-requests.php">📨 Course Requests</a>
  <a href="learner-course-materials.php">📁 Course Materials</a>

  <a href="../tutor/tutor-courses-list.php">🧑‍🏫 Tutor Courses</a>
  <a href="../tutor/tutor-course-requests.php">📬 Course Requests</a>
  <a href="../tutor/tutor-enrolled-courses.php">🧾 Enrolled Learners</a>
  <a href="../tutor/tutor-course-materials.php">📂 Course Materials</a>

  <a href="../petty-cash.php">💰 Petty Cash</a>
</nav>

<style>
  /* Header */
  .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #007cc7;
    padding: 0.6em 1em;
    color: white;
    box-shadow: 0 2px 5px rgba(0,124,199,0.4);
  }
  .header-left {
    display: flex;
    align-items: center;
  }
  .logo-link {
    display: flex;
    align-items: center;
    text-decoration: none;
    color: white;
  }
  .logo {
    height: 40px;
    margin-right: 0.9em;
  }
  .title-text h1 {
    margin: 0;
    font-size: 1.5em;
    font-weight: 700;
  }
  .title-text p {
    margin: 0;
    font-size: 0.9em;
    opacity: 0.85;
  }
  .header-right {
    display: flex;
    align-items: center;
  }
  .user-name {
    margin-right: 1.2em;
    font-weight: 600;
    font-size: 1em;
  }
  .logout-btn {
    background-color: #c62828;
    padding: 0.3em 0.9em;
    border-radius: 5px;
    color: white;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95em;
    transition: background-color 0.3s ease;
  }
  .logout-btn:hover {
    background-color: #8b1c1c;
  }

  /* Navigation */
  .top-nav {
    display: flex;
    flex-wrap: wrap;
    background-color: #e5f4fc;
    padding: 0.65em 1em;
    border-bottom: 3px solid #007cc7;
    font-weight: 600;
    font-size: 1em;
  }
  .top-nav a {
    margin-right: 1.3em;
    color: #007cc7;
    text-decoration: none;
    padding: 0.35em 0.7em;
    border-radius: 5px;
    transition: background-color 0.3s ease, color 0.3s ease;
  }
  .top-nav a:hover,
  .top-nav a:focus {
    background-color: #007cc7;
    color: white;
    outline: none;
  }
  @media (max-width: 760px) {
    .top-nav {
      justify-content: center;
    }
    .top-nav a {
      margin: 0.4em 1em;
    }
    .header {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.5em;
    }
    .header-right {
      margin-top: 0;
    }
  }
</style>
