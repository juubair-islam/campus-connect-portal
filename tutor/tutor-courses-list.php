<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$activePage = 'tutor';
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

$stmt = $pdo->prepare("SELECT uid, name FROM students WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$firstName = explode(' ', trim($student['name']))[0];

// Fetch tutor's courses
$stmt = $pdo->prepare("SELECT * FROM courses WHERE tutor_uid = ? ORDER BY created_at DESC");
$stmt->execute([$student['uid']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Tutor Courses - Campus Connect</title>
  <link rel="stylesheet" href="../css/student.css" />
  <style>
    main {
      max-width: 900px;
      margin: 2em auto;
      background: #e5f4fc;
      padding: 1.5em;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0,124,199,0.15);
    }
    h2 {
      margin-bottom: 1em;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th, td {
      padding: 0.75em 1em;
      border-bottom: 1px solid #007cc7;
      text-align: left;
      vertical-align: top;
    }
    th {
      background-color: #007cc7;
      color: white;
    }
    tr:hover {
      background-color: #d9f0ff;
    }
    .actions a, .details a {
      margin-right: 0.75em;
      color: #007cc7;
      font-weight: 600;
      text-decoration: none;
    }
    .actions a:hover, .details a:hover {
      text-decoration: underline;
    }
    .no-courses {
      font-style: italic;
      color: #555;
      padding: 1em 0;
    }
    .create-course-btn {
      display: inline-block;
      margin-bottom: 1em;
      background-color: #007cc7;
      color: white;
      padding: 0.6em 1.2em;
      border-radius: 5px;
      text-decoration: none;
      font-weight: 600;
      transition: background-color 0.3s ease;
    }
    .create-course-btn:hover {
      background-color: #005fa3;
    }


/* Table action buttons */
.actions a, .details a {
    display: inline-block;
    padding: 5px 12px;
    font-size: 0.9em;
    font-weight: 600;
    border-radius: 5px;
    text-decoration: none; /* Remove underline */
    transition: background-color 0.3s ease, color 0.3s ease;
    margin-right: 5px; /* space between buttons */
}

/* View Details button */
.details a {
    background-color: #007cc7;
    color: white;
}

.details a:hover {
    background-color: #005fa3;
}

/* Edit button */
.actions a[href*="edit"] {
    background-color: #28a745; /* green */
    color: white;
}

.actions a[href*="edit"]:hover {
    background-color: #1e7e34;
}

/* Delete button */
.actions a[href*="delete"] {
    background-color: #dc3545; /* red */
    color: white;
}

.actions a[href*="delete"]:hover {
    background-color: #b02a37;
}

/* Ensure Edit and Delete are inline without wrapping */
.actions {
    white-space: nowrap;
}



/* Bold Course Code and Course Name */
table tbody tr td:nth-child(1){
    font-weight: 700; /* bold */
}
/* Table borders and separation */
table {
    width: 100%;
    border-collapse: collapse; /* keeps borders together */
}

th, td {
    padding: 0.75em 1em;
    border: 1px solid #007cc7; /* full border around each cell */
    text-align: left;
    vertical-align: top;
}

th {
    background-color: #007cc7;
    color: white;
    font-weight: 700;
}

tr:hover {
    background-color: #d9f0ff;
}

.top-nav .active .dropbtn {
    background-color: #007cc7;
    color: white;
    border-radius: 4px;
}
.top-nav .active .dropbtn {
    background-color: #007cc7;
    color: white;
    border-radius: 4px;
}



  </style>
  <script>
    function confirmDelete(courseName) {
      return confirm("Are you sure you want to delete the course: " + courseName + " ?");
    }
  </script>
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
  <a href="/StudentProfile.php" class="<?= $activePage === 'profile' ? 'active' : '' ?>">Profile</a>
  <a href="/lost-found.php" class="<?= $activePage === 'lostfound' ? 'active' : '' ?>">Lost &amp; Found</a>
  <a href="/cctv-reporting.php" class="<?= $activePage === 'cctv' ? 'active' : '' ?>">CCTV Reporting</a>
  <a href="/event-booking.php" class="<?= $activePage === 'event' ? 'active' : '' ?>">Event Booking</a>

  <!-- Tutor Menu -->
  <div class="dropdown <?= $activePage === 'tutor' ? 'active' : '' ?>">
    <span class="dropbtn">Tutor ▾</span>
    <div class="dropdown-content">
      <a href="campus-connect-portal/tutor/tutor-courses-list.php">My Courses</a>
      <a href="/tutor/tutor-course-requests.php">Course Requests</a>
    </div>
  </div>

  <!-- Learner Menu -->
  <div class="dropdown <?= $activePage === 'learner' ? 'active' : '' ?>">
    <span class="dropbtn">Learner ▾</span>
    <div class="dropdown-content">
      <a href="/learner/learner-courses-list.php">Find Course</a>
      <a href="/learner/learner-enrolled-courses.php">Enrolled Courses</a>
    </div>
  </div>
</nav>
<main>
  <h2><?php echo htmlspecialchars($firstName); ?>'s Tutor Courses</h2>

  <?php if (!$courses): ?>
    <p class="no-courses">You have not created any courses yet. <a href="tutor-courses-create.php">Create your first course</a>.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Course Code</th>
          <th>Course Name</th>
          <th>Available Days</th>
          <th>Time</th>
          <th>Description</th>
          <th>Details</th> <!-- New Details column -->
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($courses as $course): ?>
          <tr>
            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
            <td><?php echo htmlspecialchars($course['available_days']); ?></td>
            <td><?php echo htmlspecialchars($course['start_time']) . " - " . htmlspecialchars($course['end_time']); ?></td>
            <td><?php echo nl2br(htmlspecialchars($course['description'])); ?></td>
            <td class="details">
              <a href="tutor-course-details.php?course_id=<?php echo $course['course_id']; ?>">View</a>
            </td>
            <td class="actions">
              <a href="tutor-course-edit.php?course_id=<?php echo $course['course_id']; ?>">Edit</a>
              <a href="tutor-courses-delete.php?course_id=<?php echo $course['course_id']; ?>" onclick="return confirmDelete('<?php echo htmlspecialchars(addslashes($course['course_name'])); ?>')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>

</body>
</html>
