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

// Fetch enrolled courses details
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

// Fetch materials
$materialsByCourse = [];
if ($enrolledCourses) {
    $courseIds = array_column($enrolledCourses, 'course_id');
    $inQuery = implode(',', array_fill(0, count($courseIds), '?'));
    $stmtMat = $pdo->prepare("SELECT material_id, course_id, title, description, file_data, file_name, file_type, upload_date FROM course_materials WHERE course_id IN ($inQuery) ORDER BY upload_date DESC");
    $stmtMat->execute($courseIds);
    $materials = $stmtMat->fetchAll(PDO::FETCH_ASSOC);
    foreach ($materials as $mat) {
        $materialsByCourse[$mat['course_id']][] = $mat;
    }
}

function formatTimeRange($start, $end) {
    return date('h:i A', strtotime($start)) . " - " . date('h:i A', strtotime($end));
}

// Function to display material inline
function displayMaterial($material) {
    $fileType = $material['file_type'];
    $data = base64_encode($material['file_data']);
    $src = "data:$fileType;base64,$data";
    
    $inlineDisplay = '';
    if (str_contains($fileType, 'image/')) {
        $inlineDisplay = "<img src='$src' alt='".htmlspecialchars($material['title'])."' style='max-width:300px; display:block; margin:0.5em 0;' />";
    } elseif (str_contains($fileType, 'pdf')) {
        $inlineDisplay = "<iframe src='$src' width='100%' height='400px'></iframe>";
    } else {
        $inlineDisplay = "<a href='$src' target='_blank'>View ".htmlspecialchars($material['title'])."</a>";
    }
    return $inlineDisplay;
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
  /* --- Styles preserved from original --- */


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



h2 { color: #007cc7; }
form.search-form { margin-bottom: 1em; text-align: right; }
form.search-form input[type="text"] { padding: 0.4em 0.6em; border: 1px solid #007cc7; border-radius: 4px; font-size: 1em; width: 280px; }
form.search-form button { background-color: #007cc7; border: none; color: white; padding: 0.5em 1em; font-size: 1em; border-radius: 4px; cursor: pointer; margin-left: 0.5em; transition: background-color 0.3s ease; }
form.search-form button:hover { background-color: #005fa3; }
table { width: 100%; border-collapse: collapse; margin-top: 0.3em; font-size: 0.95em; }
th, td { padding: 0.7em; border: 1px solid #007cc7; vertical-align: top; text-align: left; }
th { background-color: #007cc7; color: white; }
.no-courses { font-style: italic; color: #555; margin-top: 1em; }
button.action-btn { background-color: #007cc7; border: none; color: white; padding: 0.35em 0.8em; font-size: 0.9em; border-radius: 4px; cursor: pointer; transition: background-color 0.3s ease; }
button.action-btn:hover { background-color: #005fa3; }
button.cancel-btn { background-color: crimson; }
button.cancel-btn:hover { background-color: darkred; }
button.pending-btn { background-color: orange; cursor: default; }
button.pending-btn:hover { background-color: orange; }

/* Modal styles */
.modal-overlay { display: none; position: fixed; z-index: 9999; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
.modal { background: white; max-width: 500px; width: 90%; padding: 1.5em; border-radius: 8px; box-shadow: 0 0 15px rgba(0,124,199,0.3); }
.modal h3 { margin-top: 0; color: #007cc7; }
.modal p { margin: 0.3em 0; }
.modal-buttons { margin-top: 1.2em; text-align: right; }
.modal-buttons button { margin-left: 0.7em; padding: 0.4em 1em; border-radius: 5px; border: none; font-size: 1em; cursor: pointer; }
.modal-buttons .btn-cancel { background: #ccc; color: #333; }
.modal-buttons .btn-confirm { background: #007cc7; color: white; }
.modal-buttons .btn-confirm:hover { background: #005fa3; }

/* Message box */
#messageBox { display: none; position: fixed; top: 15px; right: 15px; background: #007cc7; color: white; padding: 1em 1.5em; border-radius: 6px; box-shadow: 0 0 10px rgba(0,124,199,0.7); z-index: 11000; font-weight: 600; }
#messageBox.error { background: #c0392b; }

footer.footer { background: #0f172a; color: #e2e8f0; text-align: center; padding: 20px 0; user-select: none; margin-top: auto; }


  body { font-family: Arial, sans-serif; }
  main { flex: 1; max-width: 900px; margin: 1em auto; background: #e5f4fc; padding: 1.5em; border-radius: 8px; box-shadow: 0 0 10px rgba(0,124,199,0.15); }
  h2.course-title { margin-top: 2em; color: #007cc7; border-bottom: 2px solid #007cc7; padding-bottom: 0.2em; }
  .course-details p { margin: 0.3em 0; }
  .materials-list { margin-top: 1em; padding-left: 1em; }
  .material-item { margin-bottom: 0.7em; }
  .material-title { font-weight: 600; color: #007cc7; }
  .material-description { font-style: italic; color: #555; }
  .no-courses { font-style: italic; color: #555; margin-top: 1em; }
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
    <a href="../student-dashboard.php">Home</a>
    <a href="../StudentProfile.php">Profile</a>
    <a href="../lost & found/lost-found.php">Lost &amp; Found</a>
    <a href="../tutor/tutor-dashboard.php">Tutor Panel</a>
    <a href="../learner/learner-dashboard.php"class="active">Learner Panel</a>
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
                  <?php echo displayMaterial($material); ?>
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
