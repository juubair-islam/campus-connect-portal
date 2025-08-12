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
$stmt = $pdo->prepare("SELECT course_name FROM courses WHERE course_id = ? AND tutor_uid = ?");
$stmt->execute([$course_id, $tutor['uid']]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("Course not found or you do not have permission to manage its materials.");
}

$errors = [];
$success = "";

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $file_url = null;

    if (!$title) $errors[] = "Title is required.";
    if (!$description) $errors[] = "Description is required.";

    // Check file upload
    if (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Please upload a file.";
    } elseif ($_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Error uploading file.";
    } else {
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png', 'application/zip'];
        $file_type = $_FILES['material_file']['type'];

        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Unsupported file type. Allowed: PDF, DOC, DOCX, JPEG, PNG, ZIP.";
        }
    }

    if (empty($errors)) {
        // Save file to uploads/materials folder
        $upload_dir = __DIR__ . '/../uploads/materials/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $original_name = basename($_FILES['material_file']['name']);
        $extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $new_filename = uniqid('material_', true) . '.' . $extension;
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['material_file']['tmp_name'], $destination)) {
            $file_url = 'uploads/materials/' . $new_filename;

            // Insert record into DB
            $insert = $pdo->prepare("INSERT INTO course_materials (course_id, title, description, file_url) VALUES (?, ?, ?, ?)");
            $insert->execute([$course_id, $title, $description, $file_url]);

            $success = "Material uploaded successfully!";
        } else {
            $errors[] = "Failed to move uploaded file.";
        }
    }
}

// Handle deletion of a material (via GET parameter ?delete=material_id)
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);

    // Check that material belongs to this tutor's course
    $checkStmt = $pdo->prepare("SELECT file_url FROM course_materials WHERE material_id = ? AND course_id = ?");
    $checkStmt->execute([$delete_id, $course_id]);
    $material = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($material) {
        // Delete file from server
        $file_path = __DIR__ . '/../' . $material['file_url'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Delete DB record
        $delStmt = $pdo->prepare("DELETE FROM course_materials WHERE material_id = ?");
        $delStmt->execute([$delete_id]);

        header("Location: tutor-course-materials.php?course_id=$course_id");
        exit();
    } else {
        $errors[] = "Material not found or you don't have permission to delete it.";
    }
}

// Fetch all materials for this course
$materialsStmt = $pdo->prepare("SELECT * FROM course_materials WHERE course_id = ? ORDER BY upload_date DESC");
$materialsStmt->execute([$course_id]);
$materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Manage Course Materials - Campus Connect</title>
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
    color: #007cc7;
  }
  form label {
    display: block;
    margin: 0.8em 0 0.3em;
    font-weight: 600;
  }
  form input[type=text], form textarea {
    width: 100%;
    padding: 0.5em;
    border: 1px solid #007cc7;
    border-radius: 4px;
    font-size: 1em;
    font-family: inherit;
    box-sizing: border-box;
  }
  form textarea {
    resize: vertical;
  }
  form input[type=file] {
    margin-top: 0.3em;
  }
  form button {
    margin-top: 1em;
    background-color: #007cc7;
    border: none;
    color: white;
    padding: 0.7em 1.5em;
    font-size: 1.1em;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
  }
  form button:hover {
    background-color: #005fa3;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1.2em;
  }
  th, td {
    padding: 0.7em;
    border: 1px solid #007cc7;
    text-align: left;
  }
  th {
    background-color: #007cc7;
    color: white;
  }
  a.delete-link {
    color: crimson;
    text-decoration: none;
    font-weight: 600;
  }
  a.delete-link:hover {
    text-decoration: underline;
  }
  .error-msg {
    color: crimson;
    margin-top: 1em;
  }
  .success-msg {
    color: green;
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
    <span class="user-name"><?php echo htmlspecialchars($tutor['name']); ?></span>
    <a href="../logout.php" class="logout-btn">Logout</a>
  </div>
</header>

<nav class="top-nav">
  <a href="../StudentProfile.php">👤 Profile</a>
  <a href="../lost-found.php">🏷️ Lost &amp; Found</a>
  <a href="../cctv-reporting.php">📹 CCTV Reporting</a>
  <a href="../event-booking.php">📅 Event Booking</a>
  <a href="../learner/learner-dashboard.php">🎓 Learner Panel</a>
  <a href="tutor-courses-list.php">📝 Tutor Courses</a>
  <a href="tutor-enrolled-courses.php">📚 Enrolled Learners</a>
</nav>

<main>
  <h2 class="course-title">Manage Materials for: <?php echo htmlspecialchars($course['course_name']); ?></h2>

  <?php if ($errors): ?>
    <div class="error-msg">
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?php echo htmlspecialchars($e); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" novalidate>
    <label for="title">Material Title <sup style="color:red">*</sup></label>
    <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" />

    <label for="description">Description <sup style="color:red">*</sup></label>
    <textarea id="description" name="description" rows="3" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

    <label for="material_file">Upload File <sup style="color:red">*</sup></label>
    <input type="file" id="material_file" name="material_file" accept=".pdf,.doc,.docx,.jpeg,.jpg,.png,.zip" required />

    <button type="submit">Upload Material</button>
  </form>

  <?php if (empty($materials)): ?>
    <p>No materials uploaded yet.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Title</th>
          <th>Description</th>
          <th>File</th>
          <th>Uploaded At</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($materials as $mat): ?>
          <tr>
            <td><?php echo htmlspecialchars($mat['title']); ?></td>
            <td><?php echo nl2br(htmlspecialchars($mat['description'])); ?></td>
            <td><a href="../<?php echo htmlspecialchars($mat['file_url']); ?>" target="_blank" rel="noopener">View</a></td>
            <td><?php echo date("M d, Y H:i", strtotime($mat['upload_date'])); ?></td>
            <td><a href="?course_id=<?php echo $course_id; ?>&delete=<?php echo $mat['material_id']; ?>" class="delete-link" onclick="return confirm('Are you sure you want to delete this material?');">Delete</a></td>
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
