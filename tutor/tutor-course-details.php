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

$course_id = $_GET['course_id'] ?? null;
if (!$course_id || !is_numeric($course_id)) {
    die("Invalid course ID.");
}

// Verify that this course belongs to this tutor
$stmt = $pdo->prepare("SELECT course_code, course_name FROM courses WHERE course_id = ? AND tutor_uid = ?");
$stmt->execute([$course_id, $tutor['uid']]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Course not found or you do not have permission to view it.");
}

$errors = [];
$success = "";

// -----------------
// Handle Unenroll
// -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_unenroll'])) {
    $learner_uid = trim($_POST['learner_uid']);

    $checkEnroll = $pdo->prepare("
        SELECT 1 FROM course_enrollments ce
        JOIN courses c ON ce.course_id = c.course_id
        WHERE ce.course_id = ? AND ce.learner_uid = ? AND c.tutor_uid = ?
    ");
    $checkEnroll->execute([$course_id, $learner_uid, $tutor['uid']]);
    
    if ($checkEnroll->fetch()) {
        $pdo->beginTransaction();

        // Delete enrollment
        $delEnroll = $pdo->prepare("DELETE FROM course_enrollments WHERE course_id = ? AND learner_uid = ?");
        $delEnroll->execute([$course_id, $learner_uid]);

        // Delete accepted request so learner can send again
        $delRequest = $pdo->prepare("DELETE FROM course_requests WHERE course_id = ? AND learner_uid = ? AND status='accepted'");
        $delRequest->execute([$course_id, $learner_uid]);

        $pdo->commit();
        $success = "Learner unenrolled successfully!";
    } else {
        $errors[] = "Learner not found in this course or permission denied.";
    }
}

// -----------------
// Handle file upload directly in DB
// -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!$title) $errors[] = "Title is required.";
    if (!$description) $errors[] = "Description is required.";

    if (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Please upload a file.";
    } elseif ($_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Error uploading file.";
    }

    if (empty($errors)) {
        $file_tmp = $_FILES['material_file']['tmp_name'];
        $file_name = $_FILES['material_file']['name'];
        $file_type = $_FILES['material_file']['type'];
        $file_data = file_get_contents($file_tmp); // Direct binary content

        $insert = $pdo->prepare("
            INSERT INTO course_materials (course_id, title, description, file_data, file_name, file_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([$course_id, $title, $description, $file_data, $file_name, $file_type]);
        $success = "Material uploaded successfully!";
    }
}

// Handle material deletion
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $checkStmt = $pdo->prepare("SELECT * FROM course_materials WHERE material_id = ? AND course_id = ?");
    $checkStmt->execute([$delete_id, $course_id]);
    $material = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($material) {
        $delStmt = $pdo->prepare("DELETE FROM course_materials WHERE material_id = ?");
        $delStmt->execute([$delete_id]);
        header("Location: tutor-course-details.php?course_id=$course_id");
        exit();
    } else {
        $errors[] = "Material not found or permission denied.";
    }
}

// Fetch enrolled learners
$enrollStmt = $pdo->prepare("
    SELECT s.name AS learner_name, ce.enrollment_date, s.uid AS learner_uid
    FROM course_enrollments ce
    JOIN students s ON ce.learner_uid = s.uid
    WHERE ce.course_id = ?
    ORDER BY ce.enrollment_date DESC
");
$enrollStmt->execute([$course_id]);
$enrolledLearners = $enrollStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch materials
$materialsStmt = $pdo->prepare("SELECT * FROM course_materials WHERE course_id = ? ORDER BY upload_date DESC");
$materialsStmt->execute([$course_id]);
$materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Course Details - <?php echo htmlspecialchars($course['course_name']); ?></title>
<link rel="stylesheet" href="../css/student.css" />
<style>
main { max-width: 900px; margin: 1em auto; background: #e5f4fc; padding: 1.5em; border-radius: 8px; box-shadow: 0 0 10px rgba(0,124,199,0.15); }
h2.course-title { color: #007cc7; margin-bottom: 1em; border-bottom: 2px solid #007cc7; padding-bottom: 0.2em; }
h3.section-title { color: #005b9f; margin-top: 1.5em; }
form label { display: block; margin: 0.8em 0 0.3em; font-weight: 600; }
form input[type=text], form textarea { width: 100%; padding: 0.5em; border: 1px solid #007cc7; border-radius: 4px; }
form textarea { resize: vertical; }
form input[type=file] { margin-top: 0.3em; }
form button { margin-top: 1em; background-color: #007cc7; border: none; color: white; padding: 0.7em 1.5em; border-radius: 5px; cursor: pointer; }
form button:hover { background-color: #005fa3; }
table { width: 100%; border-collapse: collapse; margin-top: 0.7em; }
th, td { padding: 0.6em; border: 1px solid #007cc7; text-align: left; }
th { background-color: #007cc7; color: white; }
a.delete-link, a.unenroll-link { color: crimson; text-decoration: none; font-weight: 600; }
a.delete-link:hover, a.unenroll-link:hover { text-decoration: underline; }
.error-msg { color: crimson; margin-top: 1em; }
.unenroll-btn { background-color: crimson; color: white; padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; font-size: 0.9em; }
.unenroll-btn:hover { background-color: darkred; }
#unenrollModal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
.modal-content { background: white; width: 400px; margin: 15% auto; padding: 20px; border-radius: 8px; text-align: center; }
.modal-content h3 { margin-bottom: 15px; }
.modal-content button { padding: 8px 16px; border: none; cursor: pointer; margin: 5px; border-radius: 4px; }
.confirm-btn { background: crimson; color: white; }
.cancel-btn { background: #ccc; color: black; }
.modal-success { background: #d4edda; color: #155724; padding: 10px; margin-bottom: 10px; border-radius: 4px; }
.modal-error { background: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 10px; border-radius: 4px; }
.success-msg { background: #d4edda; color: #155724; padding: 10px; margin-bottom: 10px; border-radius: 4px; }

/* Material display */
.material-preview { margin: 10px 0; padding: 10px; border: 1px solid #007cc7; border-radius: 6px; background: #f0faff; }
.material-preview iframe, .material-preview img { width: 100%; height: 500px; }
nav.top-nav { display: flex; background: #e5f4fc; padding: 10px 20px; flex-wrap: wrap; }
nav.top-nav a { margin-right: 15px; text-decoration: none; padding: 8px 12px; color: #007cc7; font-weight: bold; border-radius: 5px; transition: 0.3s; }
nav.top-nav a.active, nav.top-nav a:hover { background: #007cc7; color: #fff; }


</style>
<script>
function openUnenrollModal(uid) {
    document.getElementById('unenroll_uid').value = uid;
    document.getElementById('unenrollModal').style.display = 'block';
}
function closeUnenrollModal() {
    document.getElementById('unenrollModal').style.display = 'none';
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
    <span class="user-name"><?php echo htmlspecialchars($tutor['name']); ?></span>
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
  <h2 class="course-title"><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></h2>

  <?php if ($errors): ?>
    <div class="error-msg"><ul><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <h3 class="section-title">📚 Enrolled Learners</h3>
  <?php if (empty($enrolledLearners)): ?>
    <p>No learners enrolled in this course yet.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Learner Name</th><th>Enrollment Date</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($enrolledLearners as $learner): ?>
          <tr>
            <td><?php echo htmlspecialchars($learner['learner_name']); ?></td>
            <td><?php echo date("M d, Y H:i", strtotime($learner['enrollment_date'])); ?></td>
            <td><button class="unenroll-btn" onclick="openUnenrollModal('<?php echo $learner['learner_uid']; ?>')">Unenroll</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h3 class="section-title">📁 Upload Materials</h3>
  <form method="POST" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="upload_material" value="1">
    <label>Material Title *</label>
    <input type="text" name="title" required>
    <label>Description *</label>
    <textarea name="description" rows="3" required></textarea>
    <label>Upload File *</label>
    <input type="file" name="material_file" accept=".pdf,.doc,.docx,.jpeg,.jpg,.png,.zip" required>
    <button type="submit">Upload Material</button>
  </form>

  <h3 class="section-title">📄 Uploaded Materials</h3>
  <?php if (empty($materials)): ?>
    <p>No materials uploaded yet.</p>
  <?php else: ?>
    <?php foreach ($materials as $mat): ?>
      <div class="material-preview">
        <strong><?php echo htmlspecialchars($mat['title']); ?></strong><br>
        <em><?php echo nl2br(htmlspecialchars($mat['description'])); ?></em><br><br>
        <?php
        // Display inline based on type
        $mime = $mat['file_type'];
        $data = base64_encode($mat['file_data']);
        $src = "data:$mime;base64,$data";

        if (str_contains($mime, 'pdf')) {
            echo "<iframe src='$src' frameborder='0'></iframe>";
        } elseif (str_contains($mime, 'image')) {
            echo "<img src='$src' alt='Material'>";
        } elseif (str_contains($mime, 'zip') || str_contains($mime, 'word')) {
            echo "<p>File uploaded: $mat[file_name] (Cannot preview directly)</p>";
        }
        ?>
        <a href="?course_id=<?php echo $course_id; ?>&delete=<?php echo $mat['material_id']; ?>" class="delete-link">Delete</a>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<!-- Unenroll Confirmation Modal -->
<div id="unenrollModal">
  <div class="modal-content">
    <h3>Confirm Unenroll</h3>
    <p>Are you sure you want to unenroll this learner?</p>
    <form method="POST">
      <input type="hidden" name="confirm_unenroll" value="1">
      <input type="hidden" name="learner_uid" id="unenroll_uid">
      <button type="submit" class="confirm-btn">Yes, Unenroll</button>
      <button type="button" class="cancel-btn" onclick="closeUnenrollModal()">Cancel</button>
    </form>
  </div>
</div>

<footer class="footer">
  <p>&copy; 2025 Campus Connect | Independent University, Bangladesh</p>
</footer>
</body>
</html>
