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
body { 
    display: flex; 
    flex-direction: column; 
    min-height: 100vh; 
    margin: 0; 
    background: #f5f8fa; 
    font-family: Arial, sans-serif;
}


/* --- Page Container for Center Alignment --- */
main{
    max-width: 1200px;
    margin: 0 auto;   /* centers everything */
    width: 100%;
    padding: 0 20px;  /* breathing space */
    box-sizing: border-box;
}


main h2 { margin-bottom: 20px; color: #0f172a; }

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

/* ----- Table Styling ----- */
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 0.75em 1em;
    border: 1px solid #007cc7;
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
.actions a, .details a {
    display: inline-block;
    padding: 5px 12px;
    font-size: 0.9em;
    font-weight: 600;
    border-radius: 5px;
    text-decoration: none;
    transition: background-color 0.3s ease, color 0.3s ease;
    margin-right: 5px;
}
.details a {
    background-color: #007cc7;
    color: white;
}
.details a:hover {
    background-color: #005fa3;
}
.actions a[href*="edit"] {
    background-color: #28a745;
    color: white;
}
.actions a[href*="edit"]:hover {
    background-color: #1e7e34;
}
.actions a[href*="delete"] {
    background-color: #dc3545;
    color: white;
}
.actions a[href*="delete"]:hover {
    background-color: #b02a37;
}
.actions {
    white-space: nowrap;
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
    <a href="../student-dashboard.php">Home</a>
    <a href="../StudentProfile.php">Profile</a>
    <a href="../lost & found/lost-found.php">Lost &amp; Found</a>
    <a href="tutor-dashboard.php" class="active">Tutor Panel</a>
    <a href="../learner/learner-dashboard.php">Learner Panel</a>
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
